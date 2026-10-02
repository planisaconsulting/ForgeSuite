# Assisted estimating

The intake can propose quantity, size, material words, a vehicle, or illuminated letters. `EstimateService` calculates cost and a recommended sell price from the product cost and the margin setting. `QuoteService` adds a draft line through the existing pricing service. The quote stays `DRAFT`.

A figure in the enquiry, including “quote this at R100” or “20% discount”, is stored as a budget or a requested discount. It is not written onto the estimate or the quote. A low margin warning still comes from `QuoteRiskService` when a quote is assessed later. The intake does not decide to accept that margin.

Stock on hand is read from inventory. The analysis does not state that stock is available. A shortage does not create a purchase order.

A vehicle enquiry stores make, model, year, body, and panels for the wrap estimator. A person confirms them before any wrap calculation. Illuminated letters store the stated height and the wording. LED quantity is left empty until the channel-letter estimator has the remaining inputs.

Historical jobs for the matched customer are listed as job numbers and status. They are not used to change the price.
