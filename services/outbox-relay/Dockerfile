FROM golang:1.22 AS build
WORKDIR /src
ARG GOPROXY=direct
ARG GOSUMDB=off
ARG GOINSECURE=*
ENV GOPROXY=${GOPROXY}
ENV GOSUMDB=${GOSUMDB}
ENV GOINSECURE=${GOINSECURE}
ENV GIT_SSL_NO_VERIFY=true
ENV CGO_ENABLED=0
COPY services/outbox-relay/go.mod services/outbox-relay/go.sum* ./
RUN go mod download
COPY services/outbox-relay/ ./
RUN go build -o /outbox-relay ./cmd/outbox-relay

FROM alpine:3.20
COPY --from=build /outbox-relay /usr/local/bin/outbox-relay
ENTRYPOINT ["outbox-relay"]
