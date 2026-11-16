# ITMS Administrative Application — AI Agent Handoff

## Project status
The design phase is complete. Development has not yet started. The six project design documents are included in `project_docs/`. The original module requirement notes and document samples are included in `reference_material/`.

## What you are building
A local-first, browser-based administrative application for ITMS workflows:
- Purchase Requisition (PR)
- RAB
- Contract Management
- LHSTP
- Master Data
- Dashboard
- Shared Recycle Bin
- Activity History
- Document generation and history

Initial use is on one computer, with a planned path to access from other computers over the office LAN. Core workflows must not depend on external SaaS or APIs.

## Approved technical baseline
- Laravel 13.x
- PHP 8.5.x stable
- MySQL 8.4 LTS
- Apache HTTP Server 2.4
- Laravel Blade
- Tailwind CSS 4.x
- Limited vanilla JavaScript; Alpine.js only where useful
- Composer and npm
- Git
- Laravel Migrations as schema source of truth
- Pest as default test framework
- Dompdf initially for PDF only if the required template fidelity is achieved
- PHPWord / TemplateProcessor for DOCX
- Modular monolith; one Laravel app and one MySQL database
- Database name: `itms_admin_db`
- Private file storage outside public web root

Check compatibility at setup time. Do not use pre-release runtimes. Do not change the stack without approval.

## Non-negotiable operating instructions
Read `AGENTS.md` first. It defines strict execution, scope, safety, and approval rules.

In particular:
- Implement in the exact phase order in `DEVELOPMENT_PHASES.md`.
- Do not experiment or create alternate approaches.
- Do not make unsolicited improvements.
- If you have an idea, ask the user first and present options.
- Do not repeat completed work or successful tests without a concrete reason.
- Do not ask again for decisions already recorded in the project documents.
- Do not change business rules or architecture silently.
- Preserve the existing/legacy database. Never drop, reset, truncate, or repurpose it.
- Do not start the next phase until the current phase has passed its acceptance criteria and the user instructs you to continue.

## First action
Perform **Phase 0 only** from `DEVELOPMENT_PHASES.md`: inspect the existing repository and installed environment without modifying anything. Report the findings and any compatibility gaps. Do not install packages or create/overwrite project files during Phase 0.

After Phase 0, wait for the user to direct the next step.
