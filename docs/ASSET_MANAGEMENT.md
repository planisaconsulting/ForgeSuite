# Asset management

An asset is the physical item at a customer site. It is not a product and it is not a job item.

A product is what Sign-Forge sells. A job item is what one job produced or supplied. An asset is the thing that now exists, for example `SFA-2027-00418` at ABC Motors Klerksdorp.

Assets do not copy the quote, artwork, production, installation, or proof of delivery. They link back to the original job and job item. A completed job without an asset stays valid. Creating an asset does not add the job value again to the project.

## When an asset is created

Products have `creates_customer_asset`, off by default. A pylon, lightbox, channel letters, or LED display can be switched on. Stickers, business cards, and campaign boards stay off.

Nothing is created automatically. When an eligible job item is complete, the job can suggest an asset. A quantity above one can be one grouped asset, such as 20 directional signs, or individual assets. More than 100 individual assets in one action is refused so a sticker run cannot become a thousand records.

You can also create an asset by hand, including signage Sign-Forge did not manufacture. Mark the source `THIRD_PARTY` or `UNKNOWN` and say when a date or condition was supplied by the customer.

Import uses a CSV with customer, site, asset name, type, reference, install date, warranty months, and location. Invalid rows stop the file. A matching customer reference or serial is skipped and reported. Rows are not merged.

## Number, type, and label

Numbers are `SFA-YYYY-####` from the locked counter. Types such as pylon and lightbox are records in `asset_types`, not hard-coded rules.

The label shows Sign-Forge, the asset number, a QR symbol, and the service contact from the `asset_label_contact` setting. The QR contains `SFASSET:` plus a random token. It does not contain the database id. Staff scan opens the asset. An unknown code shows an error and does not follow another URL.

## Location

The asset belongs to the customer and, when you have one, a project site and a written location such as “main entrance, northern boundary”. There is no separate customer-site address book. Optional latitude, longitude, and accuracy are a single capture. Staff are not tracked continuously. Moving an asset writes `asset_location_history` and then updates the current site. Removal sets a date, reason, and disposition. The row stays.

Replacement marks the old asset `REPLACED` and links the new `ACTIVE` asset both ways.

## Components and vehicle branding

Serviceable parts, such as power supplies and LED modules, are components. Consumables are not copied onto the asset just because they were used on the job. Replacing a part marks the old row `REPLACED` and adds a new `ACTIVE` row with `replaces_component_id`.

Vehicle branding can store fleet number, registration, make, model, and year. A VIN is not stored.

## Portal and project

The customer portal lists only that customer’s assets. Another customer’s asset URL is refused. The public QR page shows the asset number, name, and site label, then a problem form. It does not show cost, margin, supplier price, internal notes, or private documents.

A project overview counts assets by project and by site. That count is not part of commercial value.
