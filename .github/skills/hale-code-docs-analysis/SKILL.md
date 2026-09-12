---
name: hale-code-docs-analysis
description: "Analyze the Hale Laravel codebase against its product and architecture documentation. Use when tracing domain behavior, checking API or MVP coverage, comparing implementation with requirements, investigating AI generation flows, or identifying documented and implementation gaps."
argument-hint: "What area should I inspect: the whole project, a domain, an endpoint, a feature, or a documentation gap?"
user-invocable: true
disable-model-invocation: false
---

# Hale Code and Documentation Analysis

## Purpose

Build an evidence-based picture of what Hale actually does, how that matches the documented product intent, and where implementation gaps or risks remain. Keep the analysis read-only unless the user explicitly asks for code changes.

## When to Use

- Compare a feature, endpoint, domain, or MVP requirement with the current implementation.
- Trace an AI content-generation request from HTTP entry point to output.
- Review Laravel domain boundaries, authorization, asynchronous work, credits, or provider abstraction.
- Identify whether a capability is implemented, partial, documented only, missing, inconsistent, or unclear.

## Procedure

1. **Define the slice.** Identify the requested domain, endpoint, feature, or documentation question. If the request is broad, state the slice being examined and why.
2. **Read the requirements first.** Start with `README.md`, then the relevant files under `docs/`, especially `docs/architecture/domain-structure.md`, `docs/MVP_Specification_FA.md`, and `docs/Development_Roadmap_FA.md` when applicable.
3. **Find the entry point.** Inspect the relevant route and owning controller or command. Do not treat a planned endpoint, class name, or document as proof that behavior exists.
4. **Trace the owning path.** Follow directly called requests, services, jobs, models, migrations, configuration, provider adapters, and tests. Expand only when needed to verify an ownership boundary or important claim.
5. **Check the critical contracts.** Where relevant, verify authorization, validation, asynchronous behavior, idempotency, credit reservation and settlement/refund, provider isolation, error handling, persistence, and test coverage.
6. **Classify each finding.** Use exactly one of these labels: `implemented`, `partial`, `documented-only`, `missing`, `inconsistent`, or `unclear`. Explain the evidence behind the label.
7. **Separate facts from interpretation.** Treat current code as the source of truth for existing behavior. Treat `README.md` and `docs/**` as requirements, product intent, architecture guidance, or roadmap unless the user names another source of truth.
8. **Report the result.** Link to workspace-relative files and name the relevant class, method, route, migration, or test. Mention unverified areas and only ask questions that materially affect the conclusion.

## Generation Flow

For AI generation requests, trace this path when present:

`API route -> controller/request -> Creative Engine -> credit reservation -> queued generation job -> model router or AI gateway -> provider -> settlement/refund -> output media`

Check that provider details do not leak into business logic, generation remains asynchronous where required, and credit mutations flow through the ledger or credit service.

## Output Format

Start with a short conclusion. Then use these sections as relevant:

1. **واقعیت فعلی کد**: what is implemented, with file links and symbols.
2. **داکیومنت و انتظار محصول**: what the relevant documents require or describe.
3. **تطبیق و شکاف‌ها**: classified matches, partial areas, missing pieces, contradictions, and unclear points.
4. **مسیر اجرا**: the request-to-result flow when tracing behavior.
5. **تست و ریسک**: relevant tests, unverified areas, and concrete risks.
6. **ابهام‌های لازم برای تصمیم**: only decision-relevant questions.

Match the user's language when practical. Preserve code identifiers, routes, and endpoint names exactly.

## Quality Checks

Before finishing, confirm that:

- Every major conclusion has direct code or documentation evidence.
- Implemented behavior is distinguished from planned or documented behavior.
- File references point to existing workspace files.
- The analysis did not claim execution, migration, or test results that were not actually performed.
- The scope stayed focused and read-only unless implementation was explicitly requested.
