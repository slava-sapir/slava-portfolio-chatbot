# Slava Portfolio Chatbot

AI-powered WordPress portfolio assistant built as a standalone plugin.

This plugin adds a floating chatbot widget to a WordPress portfolio website. It helps visitors learn about Slava's skills, projects, services, and experience using retrieval-augmented generation (RAG), then supports lead capture when a visitor shows hiring or collaboration intent.

## Why This Project Exists

The goal is to demonstrate practical AI engineering inside a real WordPress environment:

- extracting approved site content
- generating embeddings
- storing vectors in Supabase
- retrieving relevant knowledge chunks
- grounding LLM answers with RAG
- adding guardrails to reduce unsupported claims
- capturing and managing leads from chatbot conversations

## Key Features

- Standalone WordPress plugin architecture
- Admin settings page for OpenAI, Supabase, source pages, and behavior
- Manual knowledge base refresh from selected WordPress pages
- OpenAI embeddings and chat responses
- Supabase Postgres with pgvector for similarity search
- RAG-based chat endpoint
- Guardrails for unsupported claims, pricing, availability, and private details
- Floating frontend chatbot widget
- Lightweight pre-chat survey
- Inline lead capture form
- Leads admin tab with:
  - lead table
  - full lead detail
  - conversation view
  - mark as contacted
  - delete lead
  - CSV export
- Chat logging
- Privacy notice and lead consent
- Rate limiting using WordPress transients
- Chat log retention cleanup using WordPress cron

## Tech Stack

- WordPress plugin PHP
- WordPress REST API
- WordPress Settings API
- WordPress HTTP API
- OpenAI API
- Supabase
- PostgreSQL + pgvector
- Vanilla JavaScript
- CSS

## RAG Flow

1. Admin selects approved WordPress pages.
2. Plugin cleans and chunks the page content.
3. OpenAI creates embeddings for each chunk.
4. Supabase stores documents, chunks, metadata, and vectors.
5. Visitor asks a question in the chat widget.
6. Plugin embeds the question.
7. Supabase retrieves the most relevant chunks.
8. OpenAI answers using only retrieved context and guardrails.
9. Widget displays the answer with source links.

## Security and Privacy Notes

- OpenAI and Supabase keys are stored server-side in WordPress options.
- API keys are not exposed to frontend JavaScript.
- Public REST endpoints validate and sanitize input.
- Chat and lead endpoints use basic rate limiting.
- Lead form requires consent before storing contact details.
- Chat logs store a salted session hash, not raw IP addresses.
- Old chat logs are cleaned up according to the configured retention period.

## Local Development

The plugin is designed to live in:

```text
wp-content/plugins/slava-portfolio-chatbot
```

After installing locally:

1. Activate **Slava Portfolio Chatbot** in WordPress admin.
2. Open **Portfolio Chatbot** settings.
3. Add OpenAI and Supabase credentials.
4. Select source pages.
5. Run **Refresh Knowledge Base**.
6. Enable the chatbot.
7. Test the frontend widget.

## Supabase Requirements

The project expects:

- `kb_documents` table
- `kb_chunks` table
- `match_kb_chunks` RPC function
- pgvector enabled

The schema SQL is maintained separately during development and can be run in the Supabase SQL editor.

## Project Status

MVP complete for local testing.

Recommended next improvements:

- production deployment checklist
- exact snippet citations
- chat log admin viewer
- scheduled knowledge base refresh
- analytics for questions, fallbacks, and lead conversion
- language column in leads table
- cleanup of Supabase chunks for deselected pages

