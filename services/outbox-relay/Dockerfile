FROM golang:1.22-alpine AS build
WORKDIR /src
COPY services/outbox-relay/go.mod services/outbox-relay/go.sum* ./
RUN go mod download
COPY services/outbox-relay/ ./
RUN go build -o /outbox-relay ./cmd/outbox-relay

FROM alpine:3.20
COPY --from=build /outbox-relay /usr/local/bin/outbox-relay
ENTRYPOINT ["outbox-relay"]
