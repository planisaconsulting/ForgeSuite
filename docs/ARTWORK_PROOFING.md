# Artwork proofing

A customer proof is a PDF, JPG, or PNG on `artwork_proofs`. It is labelled **PROOF · NOT FOR PRODUCTION** in the viewer. That label is text on the page. The file itself is not stamped, because this host is not required to have GD.

## Viewer

The proof viewer zooms and fits an image with the page controls. Pins and areas use normalised coordinates (`x` and `y` from 0 to 1), so a different screen keeps the same place. An area also stores `width` and `height` the same way.

A PDF stores `page_number` on each comment. Comments for page 2 are not listed on page 1. The server reads a page count only when the PDF has one `/Type /Pages` and one `/Count`. It does not draw the PDF page.

## Comments

Annotation types are `CHANGE_REQUEST`, `QUESTION`, `CORRECTION`, `INTERNAL_NOTE`, `APPROVAL_NOTE`, and `PRODUCTION_NOTE`.

Visibility is `CUSTOMER_SHARED` or `INTERNAL_ONLY`. The portal query returns only `CUSTOMER_SHARED`. A reply can sit on the annotation. A revision can also have a comment that is not on a point.

`@` mention notifies an internal user only on an internal note. The customer does not see that user.

Status is `OPEN`, `ACKNOWLEDGED`, `RESOLVED`, `WONT_CHANGE`, or `SUPERSEDED`. Resolving stores `resolved_revision_id`. Completing a design task does not resolve the annotation. Creating a task from an annotation does not create a second task if one is already linked.

Comment text is stored as entered and escaped when the page renders it.

## Comparison

`/artwork/{id}/compare` shows two proofs and an opacity control. The change summary written by the designer is the explanation of what changed. The page does not claim to understand the design, and it does not compute a pixel difference.

## Share link

A proof link is a random token. The database stores the SHA-256 hash. It expires, it can be revoked, and it is scoped to one artwork, one revision, and one proof. Permission is `VIEW`, `COMMENT`, or `APPROVE`.

Opening artwork B with artwork A’s token is refused. An approval link for R3 cannot approve R4. Approval from the link stores the name, the statement, the time, and the IP address. It does not create a customer contact.

## Reminders

Artwork still in `CUSTOMER_REVIEW` is reminded after `artwork_reminder_days` (default 3). The same artwork is not reminded again until that many days have passed. The notice uses the existing notification record. It does not send a separate email by itself.

The customer message is the job number and the revision, for example: “Your artwork proof R3 for Job SFJ-2027-0042 is ready for review.”
