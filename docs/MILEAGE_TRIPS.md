# Mileage and field trips

Mileage stores the person, vehicle label, date, origin, destination, optional odometer readings, distance, purpose, job or project, the rate per kilometre at that moment, and the cost.

The rate comes from `mileage_rate_per_km`. Changing the setting later does not change a saved trip. If both odometer readings are present, the end reading must be at least the start, and the distance is the difference. A claimed distance that is more than a kilometre away from that difference is flagged. It is not silently rewritten into a reimbursement.

A company vehicle is internal costing. It is not an employee reimbursement. A personal vehicle can be reimbursable. Neither case pays the person from this screen.

There is no continuous GPS. A location is stored only when someone records a field event that already asks for it.

A field trip is `SFTRIP-YYYY-####`. It groups mileage for several jobs. Completing the trip splits the mileage cost equally and posts one travel row per job. Completing it again does not add a second row. An existing logistics cost on the job is referenced on the trip posting. Its amount is not copied into the trip.

Courier, contractor, and stock costs stay on their own records. A subcontract expense category does not post a second job cost.
