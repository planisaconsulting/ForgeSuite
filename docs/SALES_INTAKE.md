# Sales intake

v1.1 Phase 9 adds an intake inbox at `/sales/intake`. An enquiry number is `SFIN-YYYY-####`.

Sources are email, WhatsApp, the customer portal, the website, a phone note, a walk-in, a file upload, manual entry, and other. The intake stores the original message, sender, subject, and time. It points at an existing communication, lead, or opportunity when one is supplied. It does not create a second communication log.

A reply that carries the same message id, or the intake number, stays on that intake.

Status moves from new, through review and missing information, to ready to estimate, estimate ready, and quote drafted. A service or complaint is not turned into a sales quote. Spam is a status a person can set. The analysis does not delete the row.

The workspace shows the original enquiry, the proposed customer, requirements, files, product matches, the estimate link, questions, and the next action. The next action comes from that state.

Dispatched work stays on the logistics screens. This inbox stops at a draft quote. Sending and accepting the quote stay on the normal quote workflow.
