package main

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"log"
	"os"
	"strconv"
	"strings"
	"time"

	_ "github.com/go-sql-driver/mysql"
	"github.com/segmentio/kafka-go"
)

type event struct {
	ID           string
	TenantID     int64
	Type         string
	Payload      string
	Envelope     string
	PartitionKey string
	Attempts     int
}

func main() {
	dsn := os.Getenv("DATABASE_DSN")
	brokers := splitCSV(os.Getenv("KAFKA_BROKERS"))
	topic := getenv("KAFKA_TOPIC", "mercato.events")
	outboxTable := getenv("OUTBOX_TABLE", getenv("WORDPRESS_TABLE_PREFIX", "wp_")+"mercato_event_outbox")
	interval := durationEnv("POLL_INTERVAL_MS", 1000)
	batchSize := intEnv("BATCH_SIZE", 25)

	if dsn == "" || len(brokers) == 0 {
		log.Fatal("DATABASE_DSN and KAFKA_BROKERS are required")
	}

	db, err := sql.Open("mysql", normalizeDSN(dsn))
	if err != nil {
		log.Fatal(err)
	}
	defer db.Close()
	db.SetMaxOpenConns(4)
	db.SetMaxIdleConns(2)
	db.SetConnMaxLifetime(5 * time.Minute)

	ctx := context.Background()
	writer := &kafka.Writer{
		Addr:         kafka.TCP(brokers...),
		Topic:        topic,
		RequiredAcks: kafka.RequireOne,
		BatchTimeout: 250 * time.Millisecond,
	}
	defer writer.Close()

	if err := ensureTopic(ctx, brokers, topic); err != nil {
		log.Printf("topic readiness check failed topic=%s: %v", topic, err)
	}

	log.Printf("mercato outbox relay starting topic=%s brokers=%s table=%s", topic, strings.Join(brokers, ","), outboxTable)

	for {
		processed, err := runOnce(ctx, db, writer, outboxTable, batchSize)
		if err != nil {
			log.Printf("outbox relay cycle failed: %v", err)
		}
		if processed == 0 {
			time.Sleep(interval)
		}
	}
}

func ensureTopic(ctx context.Context, brokers []string, topic string) error {
	conn, err := kafka.DialContext(ctx, "tcp", brokers[0])
	if err != nil {
		return err
	}
	defer conn.Close()

	return conn.CreateTopics(kafka.TopicConfig{
		Topic:             topic,
		NumPartitions:     1,
		ReplicationFactor: 1,
	})
}

func runOnce(ctx context.Context, db *sql.DB, writer *kafka.Writer, outboxTable string, batchSize int) (int, error) {
	events, err := claim(ctx, db, outboxTable, batchSize)
	if err != nil {
		return 0, err
	}

	for _, ev := range events {
		if err := publish(ctx, writer, ev); err != nil {
			if markErr := fail(ctx, db, outboxTable, ev, err); markErr != nil {
				return len(events), errors.Join(err, markErr)
			}
			continue
		}
		if err := succeed(ctx, db, outboxTable, ev.ID); err != nil {
			return len(events), err
		}
	}

	return len(events), nil
}

func claim(ctx context.Context, db *sql.DB, outboxTable string, batchSize int) ([]event, error) {
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return nil, err
	}
	defer tx.Rollback()

	rows, err := tx.QueryContext(ctx, fmt.Sprintf(`
		SELECT event_id, tenant_id, event_type, payload, envelope, partition_key, attempts
		FROM %s
		WHERE status IN ('pending','publishing')
		  AND COALESCE(next_attempt_at, UTC_TIMESTAMP(3)) <= UTC_TIMESTAMP(3)
		ORDER BY created_at ASC
		LIMIT ?
		FOR UPDATE SKIP LOCKED`, quoteIdentifier(outboxTable)), batchSize)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	events := []event{}
	for rows.Next() {
		var ev event
		if err := rows.Scan(&ev.ID, &ev.TenantID, &ev.Type, &ev.Payload, &ev.Envelope, &ev.PartitionKey, &ev.Attempts); err != nil {
			return nil, err
		}
		events = append(events, ev)
	}
	if err := rows.Err(); err != nil {
		return nil, err
	}

	for _, ev := range events {
		if _, err := tx.ExecContext(ctx, fmt.Sprintf(`
			UPDATE %s
			SET status = 'publishing', attempts = attempts + 1, last_error = NULL
			WHERE event_id = ?`, quoteIdentifier(outboxTable)), ev.ID); err != nil {
			return nil, err
		}
	}

	if err := tx.Commit(); err != nil {
		return nil, err
	}

	return events, nil
}

func publish(ctx context.Context, writer *kafka.Writer, ev event) error {
	return writer.WriteMessages(ctx, kafka.Message{
		Key:   []byte(ev.PartitionKey),
		Value: []byte(ev.Payload),
		Headers: []kafka.Header{
			{Key: "event_id", Value: []byte(ev.ID)},
			{Key: "event_type", Value: []byte(ev.Type)},
			{Key: "tenant_id", Value: []byte(strconv.FormatInt(ev.TenantID, 10))},
			{Key: "envelope", Value: []byte(ev.Envelope)},
		},
		Time: time.Now().UTC(),
	})
}

func succeed(ctx context.Context, db *sql.DB, outboxTable string, eventID string) error {
	_, err := db.ExecContext(ctx, fmt.Sprintf(`
		UPDATE %s
		SET status = 'published', published_at = UTC_TIMESTAMP(3), last_error = NULL
		WHERE event_id = ?`, quoteIdentifier(outboxTable)), eventID)
	return err
}

func fail(ctx context.Context, db *sql.DB, outboxTable string, ev event, publishErr error) error {
	status := "pending"
	delay := time.Duration(ev.Attempts+1) * time.Minute
	if ev.Attempts+1 >= 5 {
		status = "dlq"
		delay = 0
	}

	_, err := db.ExecContext(ctx, fmt.Sprintf(`
		UPDATE %s
		SET status = ?, last_error = ?, next_attempt_at = UTC_TIMESTAMP(3) + INTERVAL ? SECOND
		WHERE event_id = ?`, quoteIdentifier(outboxTable)), status, truncate(publishErr.Error(), 255), int(delay.Seconds()), ev.ID)
	return err
}

func normalizeDSN(dsn string) string {
	if strings.Contains(dsn, "?") {
		return dsn + "&parseTime=true&multiStatements=false"
	}
	return dsn + "?parseTime=true&multiStatements=false"
}

func quoteIdentifier(value string) string {
	return "`" + strings.ReplaceAll(value, "`", "``") + "`"
}

func splitCSV(value string) []string {
	parts := strings.Split(value, ",")
	out := []string{}
	for _, part := range parts {
		part = strings.TrimSpace(part)
		if part != "" {
			out = append(out, part)
		}
	}
	return out
}

func getenv(key string, fallback string) string {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}
	return value
}

func intEnv(key string, fallback int) int {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}
	parsed, err := strconv.Atoi(value)
	if err != nil || parsed < 1 {
		return fallback
	}
	return parsed
}

func durationEnv(key string, fallbackMS int) time.Duration {
	return time.Duration(intEnv(key, fallbackMS)) * time.Millisecond
}

func truncate(value string, max int) string {
	if len(value) <= max {
		return value
	}
	return value[:max]
}
