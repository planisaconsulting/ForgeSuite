# Production files

A production file is a row in `production_files`. It is not the customer proof.

Versions are `PF1`, `PF2`, `PF3`, separate from artwork `R1`, `R2`, `R3`. Each file stores the artwork revision, the proof when one exists, the SHA-256 hash, and the scale label copied from the artwork.

Status is `DRAFT`, `PRE_FLIGHT`, `REVIEW_REQUIRED`, `APPROVED_FOR_PRODUCTION`, `SUPERSEDED`, or `REJECTED`.

Customer artwork approval does not set `APPROVED_FOR_PRODUCTION`. A user with `artwork.production_file.approve` or `production.release.approve` does. A designer can prepare the file and cannot approve it.

## Pre-flight

Checks are `DIMENSIONS`, `BLEED`, `RESOLUTION`, `FONTS`, `IMAGES`, `COLOUR_MODE`, `CUT_CONTOUR`, `WHITE_INK`, and `NOTES`.

A new file starts every check at `NOT_AUTOMATICALLY_VERIFIED`. A person can record `HUMAN_CONFIRMED` or `FAIL`. A CDR, AI, EPS, or PSD file is never reported as colour-verified. Raster width, height, and effective DPI are read when PHP can read the image.

Effective DPI is pixels × 25.4 ÷ print millimetres. 3000 pixels across 1000 mm is 76.2. The warning threshold is `dpi_small_print` (150) or `dpi_large_format` (75) when the longer side is at least `large_format_min_mm` (1000). One number is not used for every sign.

## Release

When `production_file_required` is set and no file is `APPROVED_FOR_PRODUCTION`, readiness adds `PRODUCTION_FILE_APPROVED` as `BLOCK`. Jobs that do not set the flag are unchanged.

A release snapshot in `artwork_release_snapshots` stores the artwork revision, the proof, the production file, and the file hash. The release JSON also includes `artwork_files`.

If a production file on a `RELEASED` job is superseded, that release becomes `REVIEW_REQUIRED`. The workshop page shows the current `APPROVED_FOR_PRODUCTION` file and labels the old one **SUPERSEDED — DO NOT USE**.

Downloading an approved production file writes `artwork_download_log`. Thumbnail requests are not logged. A release scan links to that current file when one exists.

## Workshop

`/artwork/jobs/{id}/workshop` is the file the workshop should use. It does not list every historical upload as a choice of equal weight.
