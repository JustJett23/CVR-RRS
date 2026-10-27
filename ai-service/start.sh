#!/bin/sh
set -eu

: "${AI_SERVICE_TOKEN:?Set AI_SERVICE_TOKEN to a long random secret}"
umask 077
printf '%s\n' "$AI_SERVICE_TOKEN" >/tmp/llama-api-key

/app/llama-server \
    --model /models/qwen2.5-0.5b-instruct-q4_k_m.gguf \
    --host 127.0.0.1 \
    --port 11435 \
    --ctx-size 2048 \
    --threads 1 \
    --threads-batch 1 \
    --parallel 1 \
    --api-key-file /tmp/llama-api-key \
    --alias rrs-help \
    --no-webui \
    --log-disable >/tmp/llama-server.log 2>&1 &
model_pid=$!

/usr/local/bin/rrs-ai &
app_pid=$!

cleanup() {
    kill -TERM "$app_pid" "$model_pid" 2>/dev/null || true
    wait "$app_pid" 2>/dev/null || true
    wait "$model_pid" 2>/dev/null || true
    rm -f /tmp/llama-api-key
}

trap cleanup INT TERM EXIT
wait "$app_pid"
