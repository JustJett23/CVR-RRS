# RRS help AI service

This service runs a local CPU-only `llama.cpp` model and exposes only a small authenticated chat endpoint. The fixed system prompt contains synthetic RRS help guidance. It has no database connection, MCP client tools, user-account access, reservation access, or chat-history storage. It blocks common email, phone, and student-ID patterns, rejects unrelated topics, and refuses requests for live records before invoking the model. This is a guardrail, not a substitute for users avoiding personal information.

The image build downloads the instruction-tuned `Qwen2.5-0.5B-Instruct` Q4_K_M GGUF model (about 491 MB, Apache 2.0). This is inference from a small general pretrained model with a fixed synthetic context; it is not fine-tuned on CVE data. No CVE database content is provided to the model. Its answers can be wrong, so current policy and live reservation questions should go to campus support.

## Run locally with Docker

Set the same random secret in the Laravel `.env` and the shell used to start Compose:

```powershell
$env:RRS_AI_TOKEN = ([guid]::NewGuid().ToString('N') + [guid]::NewGuid().ToString('N'))
$env:RRS_AI_PORT = '8088'
docker compose -f ai-service/docker-compose.yml up --build -d
```

Set these values in the Laravel `.env`:

```dotenv
RRS_AI_URL=http://127.0.0.1:8088
RRS_AI_TOKEN=<the same generated secret>
```

The image build downloads the model and can take several minutes. Docker Desktop must be running. The first image startup loads model weights and can take longer than later replies.

## Deploy from GitHub to Vercel

Create a **separate Vercel project** connected to this same GitHub repository and set its Root Directory to `ai-service`. Vercel detects `Dockerfile.vercel`, builds the OCI container, and redeploys on pushes to the selected branch. Configure the project environment variable `AI_SERVICE_TOKEN` with a long random secret. The Docker image already contains the model; no model-provider key or database is required.

In the existing Laravel Vercel project, set:

```dotenv
RRS_AI_URL=https://<the-ai-service-domain>
RRS_AI_TOKEN=<the-same-secret-as-AI_SERVICE_TOKEN>
```

Keep the token server-side in Vercel project environment variables. Never add it to a `VITE_` variable or browser code. Preview and production environments should use separate secrets.

Vercel container functions are currently in beta, scale to zero, and have function-style CPU, memory, request-duration, and image-size limits. Hobby functions currently provide 2 GB / 1 vCPU, which is why this image uses a small CPU model. Local model inference can be slow and cold starts can take longer after an idle period. Verify the target Vercel plan and image build against current container limits before production use. For sustained use or faster answers, deploy the same image to a container host with persistent compute and point `RRS_AI_URL` at it.

## MCP sample guide

Laravel exposes a read-only MCP server at `/mcp/rrs-help`. Its single tool returns the same kind of fixed, synthetic help text and never reads live application data. The MCP endpoint and the chatbot are separate: the web chatbot calls this model service directly and does not use MCP as a database or a tool bridge.
