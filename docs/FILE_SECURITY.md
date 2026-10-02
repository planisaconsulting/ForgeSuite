# File security

Artwork files are stored under `storage/uploads/artwork/` with a random name. The original name is a column, not the path. The directory is not a public listing. Download goes through `/artwork/files/{id}/download`, `/artwork/production-files/{id}/download`, or the portal proof route.

## Visibility

`INTERNAL`, `CUSTOMER`, `SUPPLIER`, `CONTRACTOR`, or `PRODUCTION`.

A portal user can download a file only when visibility is `CUSTOMER` and the category is not `ARTWORK_SOURCE` or `FONT_REFERENCE`. Font files stay internal. A production user with `artwork.view` can open the approved production file and cannot download a source file unless they also have `artwork.files.download_source`.

Proofs belong to the job’s customer. Another customer’s id does not load them. A share token does not open a second artwork.

## Upload checks

Extensions `php`, `phtml`, `phar`, `exe`, `sh`, `js`, `html`, `htm`, and `svg` are refused. A file named `.pdf` that does not start with `%PDF` is refused. The size limit is `artwork_upload_max_mb` (32). There is no chunked upload. The form shows the error from the server.

SHA-256 is stored on proofs, artwork files, production files, and brand assets. A second copy of the same bytes is allowed and the user is told “This exact file already exists.” The hash is not a unique key, so a legitimate reuse is not blocked.

## Orphans and retention

Storage at `/artwork/storage` shows bytes, the largest files, duplicate hashes, and files with no artwork, job, or customer. Nothing is deleted automatically. `artwork_retention_note` records that approved artwork and production evidence stay.

## Search

Search matches the file name, the artwork number, or the job number, and returns one page. It does not load the file bytes.
