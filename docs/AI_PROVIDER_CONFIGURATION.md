# AI provider configuration

Assistance is off until the flags and the provider setting say otherwise. Sales intake still captures, reviews, and estimates with the flags off.

Flags:

- `AI_ASSISTANCE` is the existing master switch.
- `AI_INTAKE_ANALYSIS` allows an analysis pass.
- `AI_DOCUMENT_EXTRACTION` allows document text to be considered.
- `AI_PRODUCT_MATCH_ASSIST` is reserved for later ranking help. Product matching in this release is deterministic.
- `AI_MESSAGE_DRAFTING` lets the provider suggest question wording. The required question still comes from the missing-field rule.
- `AI_QUOTE_DESCRIPTION` is the feature name passed to the existing draft call.

Settings:

- `ai_enabled` must be `1` and `ai_provider` must be `scripted` before the stand-in runs. Any other provider name is treated as unavailable.
- `ai_model` is recorded on the analysis when a call is made.
- `intake_max_analyses` defaults to 3.
- `intake_max_input_chars` defaults to 8000.
- `intake_max_attachment_mb` defaults to 8.
- `intake_analysis_retention_days` defaults to 365. Raw provider logs follow that policy. Confirmed requirements, the estimate link, and the quote link stay with the business records.
- `intake_prompt_version` defaults to 1 and is stored on each analysis.
- `intake_prefix` defaults to SFIN.

The provider secret, when a live provider is added later, belongs in server configuration. Do not put it in `settings`, in the browser, or in the analysis row.

If the provider is unavailable, the intake status stays reviewable and the deterministic reading of the message is kept. The sales desk does not stop.
