package main

import (
	"log"
	"os"
	"time"
)

func main() {
	dsn := os.Getenv("DATABASE_DSN")
	brokers := os.Getenv("KAFKA_BROKERS")

	if dsn == "" || brokers == "" {
		log.Fatal("DATABASE_DSN and KAFKA_BROKERS are required")
	}

	log.Printf("mercato outbox relay starting with kafka=%s", brokers)

	for {
		log.Print("outbox relay scaffold tick")
		time.Sleep(30 * time.Second)
	}
}
