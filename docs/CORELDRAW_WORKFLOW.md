# CorelDRAW workflow

Sign-Forge does not open CorelDRAW and does not read a CDR file.

Use CorelDRAW on the workstation. In Sign-Forge:

1. Download the working file from the artwork workspace. That file is the source, not the customer proof.
2. Optional checkout shows who is editing. It does not lock the file on disk.
3. Export a PDF, JPG, or PNG proof and upload it as a new revision with a change class and a change summary.
4. After customer approval, export the print, cut, or CNC file and store it as a production file (`PF1`, `PF2`). Approve that file separately.

`ArtworkProofingService::manifest` returns the customer, job number, artwork number, current revision, finished size, scale, and sign kind. It is a list for the desktop folder. It is not a zip of the binaries, and there is no desktop helper in this phase.

A future helper can call:

- `GET /api/v1/artwork/{id}` to read the assigned artwork
- the staff download routes for the working file and the approved production file
- the revision and production-file POST routes to upload an export

Do not add a plugin, a CDR parser, or a RIP in that helper. Inspection of a CDR stays “Not automatically verified.”
