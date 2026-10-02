# AI safety

AI interprets. The ERP calculates. A person approves.

The provider is `AssistedIntelligenceService`. In this release the connected provider is the scripted stand-in. It drafts wording. It does not return a price, and a price in its text is not applied.

Structured extraction is validated before it is stored. The allowed object is customer, request, items, missing information, attachments, intent, and language. Item fields are description, quantity, dimensions, material words, finish, print sides, installation, notes, and an optional product id. Cost, sell, margin, VAT, and discount keys are removed and audited as ignored. A product id that does not exist is rejected. Broken JSON is a failed analysis. The enquiry stays open for manual entry.

Customer text is data. “Ignore previous instructions” does not change permissions. An attachment that says to delete customers is stored or rejected as a file. It does not delete customers.

Confirmed fields are not replaced by a later proposal. A different value is stored as a conflict until a person resolves it.

There is no loop that sends quotes, pays suppliers, releases production, or moves stock. Each of those actions stays on its own screen and permission.

Analysis of the same text is stored once. A second click returns that row. A per-intake limit comes from `intake_max_analyses`.
