# Requirement extraction

Each proposed field stores the value, the source text, the method, and a review state: proposed, confirmed, corrected, or rejected.

Evidence is one of: directly stated, inferred, missing, ambiguous, or human confirmed. The screen does not show an accuracy percentage.

Sizes are stored in millimetres. `1.2m` becomes 1200 and `80cm` becomes 800. The original fragment is kept on the line. `about`, `roughly`, and `approximately` set `is_approximate`. A pair such as `1200 x 800` is proposed as width by height and marked ambiguous until someone confirms it.

`half a dozen` proposes 6 and is marked inferred. Thickness, laminate, and installation are left empty unless the words are in the message. Customer-supplied sizes are not labelled as a site survey.

`tomorrow` and `next Friday` use the received date. The date is a request. `date_promised` stays off. Capacity and a completion promise stay on the scheduler.

Term mapping lives in `intake_term_mappings`. Perspex proposes Acrylic. Alubond and ACM propose ACM. Chromadek proposes Chromadek signage. One-way vision proposes perforated window vinyl. A mapping does not choose a material SKU unless that row has a product id.

Afrikaans enquiries are marked `AF`. A missing-field question uses the Afrikaans prompt on the rule. Customer names and artwork wording are not rewritten.

A later reply updates a proposed field. A confirmed field that disagrees with the reply is a conflict. The confirmed value stays.
