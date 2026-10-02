# Product matching

Matching runs after a person has a customer on the intake. The order is:

1. An active customer-catalogue item whose name is in the enquiry.
2. Otherwise an active product whose name matches the material term.
3. An approved specification with the same term can be suggested on that match.
4. If nothing matches, a product review request is opened. No active product is created.

States are exact, good match, partial, no match, and review required. They are not confidence scores.

`CompatibilityRuleService` runs when the match has a specification. A hard EXCLUDES rule blocks confirmation and blocks the estimate. The message from the rule is the reason.

A catalogue reorder, such as “10 more of our standard parking signs”, prefers the catalogue row over a generic product of a similar name. Current price, specification, and artwork checks still happen when that catalogue item is ordered through the customer hub. The intake does not skip them by inventing a new configuration.

Vehicle and channel-letter enquiries point at those estimators. They do not invent a sell price.
