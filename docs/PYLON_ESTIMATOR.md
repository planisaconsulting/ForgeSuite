# Pylon estimator

The pylon estimator calculates manufacturing quantities for a cabinet: frame, cladding, faces, graphics, lighting, fasteners, finish, and labour. It uses the lightbox geometry for the cabinet width, height, and depth.

Overall height is recorded. It is not a wind load, a post design, or a footing.

## Review

`PYL-STD-001` requires engineering review on every estimate that uses it. A height above the specification threshold (`height_review_mm`, 6,000 on the seed) also flags engineering review and marks the estimate outside the specification. The threshold lives on the specification. There is no global height limit in code.

The structural warning is always shown: this is a manufacturing estimate, not structural, wind-load, foundation, fire, or building-regulation certification.

## Foundation

Enter a foundation allowance as money, or record that the customer supplied an engineering design. The allowance is a typed amount. Footing dimensions are not calculated from the sign size.

## Posts and faces

A simple post uses the height configured on the specification. Above `max_post_height_mm`, the estimate flags engineering review. Face count follows the cabinet sides. Graphics are a material line when a graphic product is selected.

Hard rules on the specification still apply. The seeded pylon excludes Correx and names that exclusion in the message.
