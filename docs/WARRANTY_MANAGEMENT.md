# Warranty management

A warranty is a snapshot of the terms at installation. Changing a supplier’s catalogue later does not change the asset.

One asset can hold several warranties. Example: 24 months Sign-Forge workmanship, 60 months on the LED modules, and a separate power-supply warranty. The asset header date, when set, follows the workmanship warranty. It does not replace the others.

## Dates

The end date is the start date plus the number of calendar months, minus one day. That day is still covered.

1 January 2027 plus 24 months ends on 31 December 2028. 1 January 2029 is expired. 1 January 2027 plus 60 months ends on 31 December 2031. On 1 January 2030 the workmanship warranty has expired and the 60-month warranty is still active.

`ACTIVE`, `EXPIRING`, and `EXPIRED` follow those dates. `VOID`, `CLAIM_OPEN`, and `CLAIMED` stay as a person set them. The screen can say a warranty is likely active. It does not approve a claim.

## Claims

Claim numbers are `SFWC-YYYY-####`. Status moves from new, through assessment and the supplier, to approved, rejected, repair, resolved, or closed. A person chooses the status.

Recoverable amount and recovered amount stay on the claim. Warranty repair cost is the service job’s actual material, labour, and travel. Customer charge can be zero. That cost is not sales revenue and is not added to project commercial value or project actual cost, because the job type is not `STANDARD`.

## Alerts

The hourly cron notifies management when a warranty ends in 90, 30, or 7 days. The notice is internal. The customer is not emailed because a warranty is nearing its end.
