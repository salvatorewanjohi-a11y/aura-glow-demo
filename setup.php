<?php
/**
 * Builds the Aura Glow Spa & Salon (TJ&U Fitness Complex, 2nd floor, Rabai Road) booking store inside WordPress Playground.
 * Run by the blueprint after WooCommerce and the theme are installed.
 * Service photos are read from /wordpress/ag-images.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// The photos are already web-sized, so each one is copied straight into uploads and registered with its
// size. (media_handle_sideload opens every image, which made the demo slow to build.)
function ag_sideload( $tmp, $name, $parent, $title ) {
	$up   = wp_upload_dir();
	$file = wp_unique_filename( $up['path'], $name );
	$dest = trailingslashit( $up['path'] ) . $file;
	if ( ! @rename( $tmp, $dest ) && ! copy( $tmp, $dest ) ) {
		return new WP_Error( 'ag_copy', 'Could not copy ' . $name );
	}
	$type = wp_check_filetype( $file );
	$size = @getimagesize( $dest ) ?: [ 0, 0 ];
	$id   = wp_insert_attachment( [ 'post_mime_type' => $type['type'], 'post_title' => $title, 'post_status' => 'inherit', 'guid' => trailingslashit( $up['url'] ) . $file ], $dest, $parent, true, false );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	wp_update_attachment_metadata( $id, [ 'width' => $size[0], 'height' => $size[1], 'file' => _wp_relative_upload_path( $dest ), 'sizes' => [], 'image_meta' => [] ] );
	return $id;
}

// One transaction for the whole import: SQLite otherwise commits (and syncs to disk) after every query.
wp_defer_term_counting( true );
wp_suspend_cache_invalidation( true );
$wpdb->query( 'START TRANSACTION' );

// Fast demo build: the photos are already web-sized, so skip making thumbnails of each one.
if ( defined( 'AG_FAST' ) && AG_FAST ) {
	add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
	add_filter( 'big_image_size_threshold', '__return_false' );
	add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
	add_filter( 'woocommerce_resize_images', '__return_false' );
}

// Store settings.
foreach ( [
	'blogname'                               => 'Aura Glow Spa & Salon',
	'blogdescription'                        => 'Rejuvenate your skin, elevate your glow. Hair, nails, advanced facials and body treatments on the 2nd floor of the TJ&U Fitness Complex, Rabai Road, Buruburu.',
	'timezone_string'                        => 'Africa/Nairobi',
	'woocommerce_currency'                   => 'KES',
	'woocommerce_currency_pos'               => 'left_space',
	'woocommerce_price_num_decimals'         => '0',
	'woocommerce_price_thousand_sep'         => ',',
	'woocommerce_default_country'            => 'KE:KE30',
	'woocommerce_store_address'              => 'TJ&U Fitness Complex, 2nd floor, Rabai Road',
	'woocommerce_store_city'                 => 'Nairobi',
	'woocommerce_manage_stock'               => 'no',
	'woocommerce_allowed_countries'          => 'specific',
	'woocommerce_specific_allowed_countries' => [ 'KE' ],
	'woocommerce_ship_to_countries'          => 'disabled',
	'woocommerce_enable_reviews'             => 'yes',
	'woocommerce_review_rating_verification_label' => 'yes',
	'woocommerce_onboarding_profile'         => [ 'skipped' => true ],
	'woocommerce_task_list_hidden'           => 'yes',
	'woocommerce_coming_soon'                => 'no',
	'woocommerce_checkout_phone_field'       => 'required',
	'woocommerce_checkout_company_field'     => 'hidden',
	'woocommerce_checkout_address_2_field'   => 'hidden',
	'woocommerce_enable_coupons'             => 'no',
	'woocommerce_calc_discounts_sequentially' => 'no',
] as $k => $v ) {
	update_option( $k, $v );
}

// Remove sample content.
foreach ( get_posts( [ 'post_type' => [ 'post', 'page' ], 'name' => 'hello-world', 'numberposts' => 1 ] ) as $p ) wp_delete_post( $p->ID, true );
$sample = get_page_by_path( 'sample-page' );
if ( $sample ) wp_delete_post( $sample->ID, true );

// Service menus (order = order on the home page, in the price list and in the filters). They follow the two price
// lists on their Instagram (spa and hair, June 2025) and the tabs of their Salonist booking page.
$cat = [];
$i   = 0;
foreach ( [
	'facials'       => [ 'Facials & Skin', 'HydraFacial, high-frequency and classic facials, and skin tag removal, in our facial room.' ],
	'massage-body'  => [ 'Massage & Body', 'Swedish, deep tissue, aromatherapy and hot stone massage, body scrubs with steam, the Moroccan bath, steam and sauna, reflexology and Thai stretch therapy.' ],
	'nails'         => [ 'Nail Studio', 'Manicures and pedicures, the jelly soak pedicure, gel, tips, builder gel and acrylics.' ],
	'hair'          => [ 'Hair Salon', 'Knotless and box braids, Ghanaian lines, twists and crochet, locs and sisterlocks, wash, blow-dry and treatments, and kids\' hair.' ],
	'waxing-brows'  => [ 'Waxing & Brows', 'Body waxing from underarm to Brazilian, the vajacial, brow threading, tinting and micro shading.' ],
	'makeup'        => [ 'Makeup', 'Makeup application, full glam, special occasions and bridal makeup.' ],
	'packages'      => [ 'Signature Glow Packages', 'Head-to-toe bundles for one, for two and for brides.' ],
	'gift-vouchers' => [ 'Gift Vouchers', 'Give someone a treatment at Aura Glow: birthdays, anniversaries and bridal showers.' ],
] as $slug => [ $name, $desc ] ) {
	$t = term_exists( $slug, 'product_cat' ) ?: wp_insert_term( $name, 'product_cat', [ 'slug' => $slug, 'description' => $desc ] );
	$cat[ $slug ] = (int) $t['term_id'];
	update_term_meta( $cat[ $slug ], 'order', $i++ );
}

// Payment: nothing is paid online. The M-Pesa till isn't public, so it is sent on confirmation (PLACEHOLDER).
update_option( 'woocommerce_cod_settings', [
	'enabled'            => 'yes',
	'title'              => 'Pay at the spa after your treatment (M-Pesa or cash)',
	'description'        => 'Enjoy your treatment, then pay at the front desk on the 2nd floor by M-Pesa or cash.',
	'instructions'       => 'We will call or WhatsApp to confirm your appointment. Pay by M-Pesa or cash after your treatment.',
	'enable_for_methods' => [],
	'enable_for_virtual' => 'yes',
] );
update_option( 'woocommerce_bacs_settings', [
	'enabled'         => 'yes',
	'title'           => 'M-Pesa deposit to hold my slot',
	'description'     => 'For bridal makeup, micro shading, sisterlocks, packages and gift vouchers. We send our M-Pesa details when we confirm; the deposit comes off your bill.',
	'instructions'    => 'We will call or WhatsApp to confirm your appointment and send our M-Pesa details for the deposit. The balance is paid after your treatment.',
	'account_details' => '',
] );
update_option( 'woocommerce_bacs_accounts', [] );
update_option( 'woocommerce_cheque_settings', [ 'enabled' => 'no' ] );
update_option( 'woocommerce_gateway_order', [ 'cod' => 0, 'bacs' => 1 ] );

// Services. Every price is Aura Glow's own: the price lists on their Instagram (post DLZaBwKIjW2, June 2025) and their
// Salonist booking page (catalogue/prices.md). Times are typical durations: PLACEHOLDERS to confirm (they publish none).
// The packages are their own bundles (Salonist "Valentine Offer" tab: confirm they still run); Bridal Glow adds up
// four list prices. 'options' => [ label => price ] is a choice; 'quote' => true marks a starting price ("from").
$spa      = 'Aura Glow, 2nd floor, TJ&U Fitness Complex, Rabai Road';
$products = [
	// Facials & skin.
	[
		'slug' => 'hydrafacial', 'name' => 'HydraFacial', 'cats' => [ 'facials' ],
		'badge' => 'Signature facial', 'perk' => 'No downtime, just glow', 'perks' => 'Deep cleanse | Hydration boost | Minimises pores & fine lines', 'time' => 'About 60 min',
		'clip' => 'hydra',
		'short' => 'Deep cleansing, exfoliation and intense hydration in one session, for an instant glow. Suits every skin type: oily, dry, sensitive or acne-prone.',
		'desc' => 'Ready for that instant glow? The HydraFacial cleanses, exfoliates and draws out congestion, then floods the skin with hydrating serums. We finish under the LED mask to calm the skin. It brightens and evens your skin tone, softens fine lines and leaves pores looking smaller, with no downtime: you walk out glowing and can go straight back to your day.',
		'price' => 7000,
		'included' => [ 'Skin check and a chat about what your skin needs', 'Cleanse and exfoliation', 'Hydra-pen extraction of blackheads and congestion', 'Hydrating serum infusion', 'LED mask to calm the skin', 'Moisturiser, SPF and aftercare advice' ],
		'specs' => [ 'Time' => 'About 60 minutes', 'Skin types' => 'Oily, dry, sensitive and acne-prone', 'Downtime' => 'None', 'Where' => 'Our facial room' ],
		'upsells' => [ 'high-frequency-facial', 'brow-care', 'back-shoulder-massage' ],
	],
	[
		'slug' => 'high-frequency-facial', 'name' => 'High Frequency Facial', 'cats' => [ 'facials' ],
		'badge' => 'Acne care', 'perk' => 'Clearer, tighter skin', 'perks' => 'Fights acne | Tightens pores | Boosts collagen', 'time' => 'About 45 min',
		'short' => 'A gentle high-frequency current after a deep cleanse: it fights acne-causing bacteria, tightens pores and boosts collagen.',
		'desc' => 'High frequency is one of our three facial treatments for flawless skin. After cleansing, a glass electrode passes a gentle, warm current over the skin. It helps clear breakouts and calm inflamed spots, tightens the look of pores and stimulates collagen. It is a good choice for oily and acne-prone skin, and pairs well with a HydraFacial.',
		'price' => 3000,
		'included' => [ 'Skin check', 'Cleanse and steam', 'High-frequency treatment over the face', 'Soothing mask', 'Moisturiser and aftercare advice' ],
		'specs' => [ 'Time' => 'About 45 minutes', 'Best for' => 'Oily and acne-prone skin', 'Downtime' => 'None', 'Where' => 'Our facial room' ],
		'upsells' => [ 'hydrafacial', 'classic-facials' ],
	],
	[
		'slug' => 'classic-facials', 'name' => 'Classic Facials', 'cats' => [ 'facials' ],
		'badge' => 'Facials', 'perk' => 'Calm, fresh, radiant', 'perks' => 'Steam & cleanse | Scrub & mask | Facial massage', 'time' => '30–60 min',
		'short' => 'Calms, refreshes and revives your natural radiance: a quick facial scrub, a deep cleansing facial, or the full facial with steam, mask and massage.',
		'desc' => 'Our classic facials are the everyday way to keep skin clear. The facial scrub buffs away dull skin. Deep cleansing adds steam and extraction for blocked pores. The full facial does all of that, then adds a mask matched to your skin and a relaxing facial massage.',
		'options' => [ 'Facial scrubbing' => 500, 'Deep cleansing' => 800, 'Full facial' => 1500 ], 'attr' => 'Facial',
		'included' => [ 'Cleanse', 'Steam and exfoliation', 'Extraction (deep cleansing and full facial)', 'Mask matched to your skin (full facial)', 'Facial massage (full facial)', 'Moisturiser' ],
		'specs' => [ 'Time' => 'About 30–60 minutes', 'Skin types' => 'All', 'Where' => 'Our facial room', 'Payment' => 'After your treatment, M-Pesa or cash' ],
		'upsells' => [ 'brow-care', 'hydrafacial' ],
	],
	[
		'slug' => 'skin-tag-removal', 'name' => 'Skin Tag Removal', 'cats' => [ 'facials' ],
		'badge' => 'Skin', 'perk' => 'Quick & tidy', 'perks' => 'Skin check first | Quick treatment | Aftercare advice', 'time' => '15–30 min', 'quote' => true,
		'short' => 'Small skin tags on the face, neck or underarms removed in a short session after a skin check. Price from KSh 2,000, depending on how many.',
		'desc' => 'We look at the skin tags first and confirm the price before we start: it depends on how many there are and where. Anything that looks unusual we will ask you to show a doctor first.',
		'price' => 2000,
		'included' => [ 'Skin check', 'Removal of the skin tags', 'Aftercare advice' ],
		'specs' => [ 'Time' => 'About 15–30 minutes', 'Price' => 'From KSh 2,000, confirmed after the skin check', 'Where' => 'Our facial room' ],
		'upsells' => [ 'classic-facials' ],
	],
	// Massage & body.
	[
		'slug' => 'massage', 'name' => 'Full-Body Massage', 'cats' => [ 'massage-body' ],
		'badge' => 'Most booked', 'perk' => 'Unwind completely', 'perks' => 'Swedish or deep tissue | Aromatherapy oils | Hot stones', 'time' => 'About 60 min',
		'clip' => 'massage',
		'short' => 'Swedish for pure relaxation, deep tissue for knots and tight muscles, aromatherapy with essential oils, or hot stones for deep warmth.',
		'desc' => 'Even the busiest people need a moment to reset. Choose the massage that suits how you feel. Swedish uses long, flowing strokes to relax you. Deep tissue works firmly into knots and tight muscles, and is great after training at the gym downstairs. Aromatherapy adds calming essential oils. Hot stone uses warm basalt stones to melt tension. Every massage is in a private room with fresh towels, and there is a shower for afterwards.',
		'options' => [ 'Swedish massage' => 2000, 'Deep tissue massage' => 3000, 'Aromatherapy massage' => 4000, 'Hot stone massage' => 6000, 'Kids massage' => 1000 ], 'attr' => 'Massage',
		'included' => [ 'Private treatment room', 'Massage oil (essential oils for aromatherapy)', 'Full-body massage', 'Fresh towels and robe', 'Shower afterwards' ],
		'specs' => [ 'Time' => 'About 60 minutes', 'Where' => 'Our massage room', 'Pressure' => 'Tell your therapist what you like', 'Payment' => 'After your treatment, M-Pesa or cash' ],
		'upsells' => [ 'body-scrub-steam', 'steam-sauna', 'reflexology' ],
	],
	[
		'slug' => 'back-shoulder-massage', 'name' => 'Back & Shoulder Massage', 'cats' => [ 'massage-body' ],
		'badge' => 'Quick relief', 'perk' => 'Ease the desk ache', 'perks' => 'Back, neck & shoulders | Hot stone option | Lunch-break friendly', 'time' => '20–30 min',
		'short' => 'A focused massage for the back, neck and shoulders, where desk days and long drives leave their knots. Add hot stones for deeper warmth.',
		'desc' => 'When you do not have an hour, a back or shoulder massage targets the tension where it builds up most. The hot stone option uses warm stones along the spine to loosen tight muscles faster.',
		'options' => [ 'Shoulder massage' => 500, 'Back massage' => 1000, 'Back massage with hot stones' => 2000 ], 'attr' => 'Massage',
		'included' => [ 'Private treatment room', 'Back, neck and shoulder massage', 'Warm stones (hot stone option)' ],
		'specs' => [ 'Time' => 'About 20–30 minutes', 'Where' => 'Our massage room', 'Good with' => 'Any facial or pedicure' ],
		'upsells' => [ 'massage', 'hydrafacial' ],
	],
	[
		'slug' => 'body-scrub-steam', 'name' => 'Body Scrub & Steam', 'cats' => [ 'massage-body' ],
		'badge' => 'Body polish', 'perk' => 'Silky, glowing skin', 'perks' => 'Steam to open pores | Full-body scrub | Detox & glow', 'time' => 'About 60 min',
		'short' => 'Steam to open the pores, then a full-body scrub that polishes away dull, dry skin and leaves it soft and glowing.',
		'desc' => 'A body polish and skin detox in one. Time in the steam warms and softens the skin, then your therapist scrubs every inch with an exfoliating glove and scrub, rinses you off and finishes with a nourishing body oil.',
		'price' => 3000,
		'included' => [ 'Steam session', 'Full-body exfoliating scrub', 'Rinse', 'Nourishing body oil', 'Robe, towels and shower' ],
		'specs' => [ 'Time' => 'About 60 minutes', 'Where' => 'Our steam and treatment rooms', 'Good with' => 'A massage afterwards', 'Payment' => 'After your treatment, M-Pesa or cash' ],
		'upsells' => [ 'massage', 'moroccan-bath' ],
	],
	[
		'slug' => 'moroccan-bath', 'name' => 'Moroccan Bath', 'cats' => [ 'massage-body' ],
		'badge' => 'Ritual', 'perk' => 'Beauty meets tradition', 'perks' => 'Black soap & steam | Kessa glove scrub | Mask & oils', 'time' => 'About 90 min',
		'clip' => 'moroccan',
		'short' => 'The traditional hammam ritual: black soap and steam, a vigorous kessa-glove scrub, a body mask and nourishing oils. Let your skin breathe and your soul relax.',
		'desc' => 'Unwind, rejuvenate, glow. The Moroccan bath is the deepest cleanse we offer. You relax in the steam with black soap to soften the skin, then your therapist scrubs you head to toe with the kessa glove to lift away dead skin. A body mask and warm rinse follow, and we finish with nourishing oils. Your skin feels brand new.',
		'price' => 5000,
		'included' => [ 'Black soap and steam', 'Head-to-toe kessa glove scrub', 'Body mask', 'Warm rinse', 'Nourishing body oil', 'Robe, towels and shower' ],
		'specs' => [ 'Time' => 'About 90 minutes', 'Where' => 'Our steam room', 'Bring' => 'Nothing: robes, towels and caps provided', 'Payment' => 'After your treatment, M-Pesa or cash' ],
		'upsells' => [ 'massage', 'hydrafacial' ],
	],
	[
		'slug' => 'steam-sauna', 'name' => 'Steam & Sauna', 'cats' => [ 'massage-body' ],
		'badge' => 'Relax', 'perk' => 'Sweat out the stress', 'perks' => 'Steam room | Sauna | Robe & towels', 'time' => 'About 30–45 min',
		'short' => 'Time in the steam and the sauna to open the pores, ease sore muscles and switch off. Perfect before a massage or a scrub.',
		'desc' => 'Heat is the simplest way to relax: it eases tired muscles, opens the pores and clears the head. Robes and towels are provided, and you can shower afterwards.',
		'price' => 3000,
		'included' => [ 'Steam session', 'Sauna session', 'Robe, towels and shower' ],
		'specs' => [ 'Time' => 'About 30–45 minutes', 'Good with' => 'Massage or body scrub', 'Health' => 'Tell us if you are pregnant or have a heart condition' ],
		'upsells' => [ 'massage', 'body-scrub-steam' ],
	],
	[
		'slug' => 'reflexology', 'name' => 'Reflexology', 'cats' => [ 'massage-body' ],
		'badge' => 'Feet', 'perk' => 'Pressure-point relief', 'perks' => 'Foot soak | Pressure-point massage | Deeply calming', 'time' => 'About 30 min',
		'short' => 'A pressure-point massage of the feet that relaxes the whole body. Tired feet after a long day will thank you.',
		'desc' => 'Reflexology works pressure points across the soles of the feet. After a warm soak, your therapist works each foot slowly and firmly. Most people are half asleep by the end.',
		'price' => 1000,
		'included' => [ 'Warm foot soak', 'Pressure-point foot massage', 'Moisturising balm' ],
		'specs' => [ 'Time' => 'About 30 minutes', 'Good with' => 'Any pedicure' ],
		'upsells' => [ 'pedicure', 'jelly-soak-pedicure' ],
	],
	[
		'slug' => 'thai-stretch-therapy', 'name' => 'Thai Stretch Therapy', 'cats' => [ 'massage-body' ],
		'badge' => 'Mobility', 'perk' => 'Stretch, recover, feel it', 'perks' => 'Assisted stretching | Better mobility | For everyone', 'time' => '30 or 60 min',
		'short' => 'Assisted stretching by our Thai stretch therapists: better mobility, faster recovery and less stiffness. Single sessions or monthly plans.',
		'desc' => 'Breaking the myth: Thai stretch therapy is not just for the ladies, as Coach Arnold showed on our page. Your therapist moves you through deep, assisted stretches on the table, opening the hips, back, hamstrings and shoulders. It is ideal after training at the gym downstairs, for desk-bound backs, or simply to move better. Book one session, or a monthly plan of 8 or 12 sessions.',
		'options' => [ 'Single session, 30 min' => 600, 'Single session, 1 hour' => 1000, '8 sessions a month, 30 min' => 4000, '8 sessions a month, 1 hour' => 7200, '12 sessions a month, 30 min' => 6400, '12 sessions a month, 1 hour' => 9600 ], 'attr' => 'Plan',
		'included' => [ 'Assisted stretching on the table', 'Hips, back, hamstrings and shoulders', 'Breathing and pacing guided by your therapist' ],
		'specs' => [ 'Time' => '30 minutes or 1 hour', 'Wear' => 'Comfortable clothes you can move in', 'Plans' => '8 or 12 sessions a month', 'Payment' => 'Single sessions after; plans paid upfront' ],
		'upsells' => [ 'massage', 'steam-sauna' ],
	],
	// Nail studio.
	[
		'slug' => 'manicure', 'name' => 'Manicure', 'cats' => [ 'nails' ],
		'badge' => 'Nails', 'perk' => 'Clean, polished hands', 'perks' => 'Soak & cuticle care | Shape & buff | Polish or gel', 'time' => '45–60 min',
		'clip' => 'nails',
		'short' => 'From polish removal to a flawless new look: soak, cuticle care, shaping and a hand massage, finished with polish or long-lasting gel.',
		'desc' => 'Your hands do the talking. We remove old polish, soak and tidy the cuticles, shape and buff the nails, scrub and massage the hands, then finish with your colour. Gel lasts two to three weeks without chipping.',
		'options' => [ 'Plain manicure' => 800, 'Manicure with gel' => 1300 ], 'attr' => 'Finish',
		'included' => [ 'Polish removal', 'Soak and cuticle care', 'Shape and buff', 'Hand scrub and massage', 'Polish or gel in your colour' ],
		'specs' => [ 'Time' => 'About 45–60 minutes', 'Gel lasts' => 'About 2–3 weeks', 'Where' => 'Our manicure bar' ],
		'upsells' => [ 'pedicure', 'gel-tips-builder', 'acrylics' ],
	],
	[
		'slug' => 'pedicure', 'name' => 'Pedicure', 'cats' => [ 'nails' ],
		'badge' => 'Feet', 'perk' => 'Soft, pretty feet', 'perks' => 'Soak & scrub | Heels smoothed | Foot massage', 'time' => '60 min',
		'short' => 'More than a pedicure: a warm soak, soothing scrub, heels smoothed, a relaxing foot massage and polish, gel or gel tips.',
		'desc' => 'Hands and feet do not lie. In our pedicure chairs your feet soak in warm water, then we tidy the cuticles, shape the nails, smooth rough heels, scrub and massage your feet and legs, and finish with your colour.',
		'options' => [ 'Plain pedicure' => 1000, 'Pedicure with gel' => 1500, 'Pedicure with gel tips' => 2500 ], 'attr' => 'Finish',
		'included' => [ 'Warm foot soak', 'Cuticle care, nail shaping', 'Rough heels smoothed', 'Foot and leg scrub', 'Foot massage', 'Polish, gel or gel tips' ],
		'specs' => [ 'Time' => 'About 60 minutes', 'Where' => 'Our pedicure chairs', 'Wear' => 'Open shoes if you choose polish' ],
		'upsells' => [ 'manicure', 'reflexology', 'jelly-soak-pedicure' ],
	],
	[
		'slug' => 'jelly-soak-pedicure', 'name' => 'Jelly Soak Pedicure', 'cats' => [ 'nails' ],
		'badge' => 'Foot therapy', 'perk' => 'Soft, soothing, satisfying', 'perks' => 'Lavender jelly soak | Full pedicure | Extra-long massage', 'time' => 'About 75 min',
		'clip' => 'jelly',
		'short' => 'The ultimate foot therapy: your feet sink into a warm lavender jelly that holds the heat, then a full pedicure and an extra-long massage.',
		'desc' => 'Our pedicure jelly soak is not just a soak: it is a whole experience. The jelly holds the warmth around your feet and ankles, softening the skin before we scrub, smooth and massage. Finished with polish or gel. Your feet will thank you later.',
		'price' => 3000,
		'included' => [ 'Warm lavender jelly soak', 'Cuticle care and nail shaping', 'Heels smoothed and scrubbed', 'Extra-long foot and leg massage', 'Polish' ],
		'specs' => [ 'Time' => 'About 75 minutes', 'Where' => 'Our pedicure chairs', 'Good with' => 'A manicure' ],
		'upsells' => [ 'manicure', 'reflexology' ],
	],
	[
		'slug' => 'gel-tips-builder', 'name' => 'Gel, Tips & Builder Gel', 'cats' => [ 'nails' ],
		'badge' => 'Nail art', 'perk' => 'Crown-worthy nails', 'perks' => 'Gel polish | Tips & stick-ons | Builder gel overlay', 'time' => '30–90 min',
		'short' => 'Gel polish on natural nails, stick-ons, tips for length, or builder gel for strength. French tips, foils and nail art on request.',
		'desc' => 'Every queen deserves crown-worthy nails. Choose plain gel on your own nails, quick stick-ons, tips for extra length, tips with builder gel, or a builder gel overlay that strengthens your natural nails. Ask your technician about French tips, gold foil and art like the sets on our Instagram.',
		'options' => [ 'Plain gel' => 500, 'Stick-ons' => 700, 'Tips' => 1000, 'Tips with gel builder' => 1200, 'Builder gel overlay' => 1500, 'Tips removal' => 200 ], 'attr' => 'Service',
		'included' => [ 'Nail prep and shaping', 'Tips or builder gel where chosen', 'Gel colour cured under the lamp', 'Cuticle oil' ],
		'specs' => [ 'Time' => 'About 30–90 minutes', 'Art' => 'French tips, foils and designs on request', 'Where' => 'Our manicure bar' ],
		'upsells' => [ 'manicure', 'acrylics' ],
	],
	[
		'slug' => 'acrylics', 'name' => 'Acrylic Nails', 'cats' => [ 'nails' ],
		'badge' => 'Acrylics', 'perk' => 'Long, strong, flawless', 'perks' => 'Full sets | Overlays | Refills', 'time' => '60–120 min',
		'short' => 'Full acrylic sets in your shape and length, overlays on natural nails, and refills to keep them flawless.',
		'desc' => 'Flawless nails, endless confidence. A full set adds length and shape: almond, coffin, square or stiletto. An overlay strengthens your own nails, and a refill fills the regrowth every two to three weeks. Colours, French tips and art on request.',
		'options' => [ 'Full acrylic set' => 3000, 'Acrylic overlay' => 2000, 'Acrylic refill' => 1500 ], 'attr' => 'Service',
		'included' => [ 'Nail prep', 'Acrylic applied and shaped', 'Filed and buffed', 'Colour of your choice', 'Cuticle oil' ],
		'specs' => [ 'Time' => 'About 1–2 hours', 'Refill' => 'Every 2–3 weeks', 'Where' => 'Our manicure bar' ],
		'upsells' => [ 'pedicure', 'gel-tips-builder' ],
	],
	// Hair salon.
	[
		'slug' => 'braids', 'name' => 'Braids & Ghanaian Lines', 'cats' => [ 'hair' ],
		'badge' => 'Braids', 'perk' => 'Neat parts, every time', 'perks' => 'Knotless & box braids | Stitch-parted lines | Neat, even parts', 'time' => '3–6 hours', 'quote' => true,
		'clip' => 'braids',
		'short' => 'Knotless braids, box braids and Ghanaian lines with clean, precise parts. Prices start from the amounts shown and depend on length and size.',
		'desc' => 'Precision, style and glow. Our braiders section every part cleanly, from stitch-parted box braids to knotless braids that sit light on the scalp. Ghanaian lines are braided close to the scalp in straight or curved designs. Bring your braiding hair or ask us to source it. The price depends on length and size, and we confirm it before we start.',
		'options' => [ 'Box braids (from)' => 1000, 'Knotless braids (from)' => 1200, 'Ghanaian lines (from)' => 1500 ], 'attr' => 'Style',
		'included' => [ 'Consultation on size, length and style', 'Sectioning and parting', 'Braiding', 'Ends finished', 'Edges laid' ],
		'specs' => [ 'Time' => 'About 3–6 hours, depending on size and length', 'Hair' => 'Bring yours, or ask us to source it', 'Price' => 'Starting prices, confirmed before we begin' ],
		'upsells' => [ 'wash-treatment', 'twists-crochet' ],
	],
	[
		'slug' => 'twists-crochet', 'name' => 'Twists & Crochet', 'cats' => [ 'hair' ],
		'badge' => 'Twists', 'perk' => 'Protective & stylish', 'perks' => 'Twists & spring twists | Crochet & boho | Defined parts', 'time' => '2–5 hours', 'quote' => true,
		'short' => 'Defined parts, flawless twists: two-strand twists, spring twists and crochet styles, including boho curls. Protective, stylish and effortless.',
		'desc' => 'Elegance in every twist. Choose classic two-strand twists, bouncy spring twists, or crochet for a quick protective style, from Marley twists to boho curls. Prices start from the amounts shown and depend on the look.',
		'options' => [ 'Twists' => 1000, 'Crochet (from)' => 1500, 'Spring twists' => 2000 ], 'attr' => 'Style',
		'included' => [ 'Consultation on the look', 'Sectioning and parting', 'Twisting or crochet installation', 'Finish and edges' ],
		'specs' => [ 'Time' => 'About 2–5 hours', 'Hair' => 'Bring yours, or ask us to source it', 'Price' => 'Starting prices, confirmed before we begin' ],
		'upsells' => [ 'wash-treatment', 'braids' ],
	],
	[
		'slug' => 'locs-weaving', 'name' => 'Locs, Sisterlocks & Weaving', 'cats' => [ 'hair' ],
		'badge' => 'Locs', 'perk' => 'Retouched & treated', 'perks' => 'Loc retouch | Sisterlocks | Tracks & sew-in', 'time' => '1 hour–2 days',
		'short' => 'Loc retouches and styling, invisible locs, sisterlocks installation and retouch, and tracks with a sew-in weave.',
		'desc' => 'Love is in the hair. We retouch, treat and style dreadlocks, install invisible locs, and offer sisterlocks: the full installation, then a retouch as they grow. Tracks and sew-in weaves are done neatly and flat for a natural finish. Sisterlocks take a long session and are booked with a deposit.',
		'options' => [ 'Tracks & sew-in' => 1500, 'Dreadlock retouch' => 1500, 'Invisible locs' => 2500, 'Sisterlock retouch' => 10000, 'Sisterlocks installation' => 20000 ], 'attr' => 'Service',
		'included' => [ 'Consultation', 'Wash where needed', 'Retouch, installation or sew-in', 'Styling and finish' ],
		'specs' => [ 'Time' => 'From about 1 hour (retouch) to 2 days (sisterlocks)', 'Deposit' => 'Sisterlocks are booked with an M-Pesa deposit', 'Where' => 'Our salon' ],
		'upsells' => [ 'wash-treatment' ],
	],
	[
		'slug' => 'wash-treatment', 'name' => 'Wash, Blow-Dry & Treatments', 'cats' => [ 'hair' ],
		'badge' => 'Hair spa', 'perk' => 'Steam. Treat. Slay.', 'perks' => 'Wash & blow-dry | Steam treatment | Leave-in hydration', 'time' => '30–90 min',
		'short' => 'Wash and blow-dry, wash and straightening, and treatments under the steamer, the hydration and shine your crown deserves.',
		'desc' => 'We are not just doing hair, we are reviving it. A deep treatment under the hair steamer opens the cuticle so the product goes deep, for real hydration and shine. Bring your own treatment for the lower price, or use ours. A leave-in treatment keeps the moisture locked in.',
		'options' => [ 'Wash & straightening' => 300, 'Wash & blow-dry' => 400, 'Treatment (your own product)' => 700, 'Leave-in treatment' => 1000, 'Deep treatment with steam' => 1200 ], 'attr' => 'Service',
		'included' => [ 'Shampoo at the basin', 'Treatment under the steamer (treatments)', 'Blow-dry or straightening', 'Styling finish' ],
		'specs' => [ 'Time' => 'About 30–90 minutes', 'Where' => 'Our salon and wash basins', 'Own product' => 'Bring it along for the lower price' ],
		'upsells' => [ 'braids', 'twists-crochet' ],
	],
	[
		'slug' => 'kids-hair', 'name' => 'Kids\' Hair', 'cats' => [ 'hair' ],
		'badge' => 'Kids', 'perk' => 'Neat for school', 'perks' => 'Wash & lines | Braids with hair | Gentle treatment', 'time' => '30 min–3 hours',
		'short' => 'Gentle, neat styles for children: wash and lines, braids with the hair included, kids\' treatments and undoing old styles.',
		'desc' => 'Back to school sorted. Our stylists are patient with little ones and keep styles neat and gentle on the edges. Kids\' braids include the braiding hair.',
		'options' => [ 'Undoing' => 350, 'Wash & lines' => 800, 'Kids treatment' => 800, 'Kids braids (hair included)' => 1500 ], 'attr' => 'Service',
		'included' => [ 'Gentle detangling', 'Wash where included', 'Style', 'Edges kept neat' ],
		'specs' => [ 'Time' => 'About 30 minutes to 3 hours', 'Hair' => 'Included for kids\' braids', 'Ages' => 'Ask us about little ones' ],
		'upsells' => [ 'wash-treatment' ],
	],
	// Waxing & brows.
	[
		'slug' => 'waxing', 'name' => 'Waxing', 'cats' => [ 'waxing-brows' ],
		'badge' => 'Waxing', 'perk' => 'Smooth for weeks', 'perks' => 'Precise & hygienic | Underarm to Brazilian | Vajacial', 'time' => '15–60 min',
		'short' => 'Precise, hygienic waxing from lip and chin to full leg, Brazilian and the manzilian, plus the vajacial to soothe and brighten the bikini area.',
		'desc' => 'Smooth skin that lasts three to five weeks. We wax in a private room with fresh wax and disposables for every client. The vajacial is a facial for the bikini area: cleansing, exfoliation and a calming mask against ingrown hairs and dark spots.',
		'options' => [ 'Underarm' => 600, 'Lip & chin' => 600, 'Half leg' => 1000, 'Chest' => 1000, 'Bikini' => 1500, 'Full leg' => 2000, 'Brazilian' => 2000, 'Manzilian' => 2000, 'Underarm & Brazilian' => 2400, 'Vajacial' => 3000 ], 'attr' => 'Area',
		'included' => [ 'Private treatment room', 'Skin prepped and cleansed', 'Waxing with fresh wax and disposables', 'Soothing after-wax lotion' ],
		'specs' => [ 'Time' => 'About 15–60 minutes', 'Hair length' => 'About 0.5 cm (2–3 weeks of growth)', 'Lasts' => 'About 3–5 weeks', 'Where' => 'A private treatment room' ],
		'upsells' => [ 'brow-care', 'pedicure' ],
	],
	[
		'slug' => 'brow-care', 'name' => 'Brow Care', 'cats' => [ 'waxing-brows' ],
		'badge' => 'Brows', 'perk' => 'Framed & defined', 'perks' => 'Threading | Tweezing & wax | Tinting', 'time' => '10–30 min',
		'short' => 'Brows shaped by threading, tweezing or wax, and tinted for definition. Quick, precise and the perfect add-on to a facial.',
		'desc' => 'Good brows frame the whole face. We map the shape that suits you, then thread, tweeze or wax, and tint them if you want more depth.',
		'options' => [ 'Threading' => 300, 'Tweezing' => 300, 'Brow wax' => 400, 'Brow tinting' => 500 ], 'attr' => 'Service',
		'included' => [ 'Brow mapping', 'Shaping by threading, tweezing or wax', 'Tint (tinting)', 'Soothing gel' ],
		'specs' => [ 'Time' => 'About 10–30 minutes', 'Good with' => 'Any facial or makeup' ],
		'upsells' => [ 'micro-shading', 'classic-facials', 'makeup' ],
	],
	[
		'slug' => 'micro-shading', 'name' => 'Micro Shading & Microblading Retouch', 'cats' => [ 'waxing-brows' ],
		'badge' => 'Semi-permanent', 'perk' => 'Wake up with brows', 'perks' => 'Soft powder brows | Mapped to your face | Retouch available', 'time' => '2–3 hours',
		'short' => 'Semi-permanent micro shading for soft, filled-in powder brows, mapped to your face, and retouches for existing microblading.',
		'desc' => 'Micro shading deposits pigment in tiny dots for a soft, powdered brow that lasts. We map the shape and choose the shade with you first. A retouch refreshes existing microblading or shading. Booked with a deposit.',
		'options' => [ 'Microblading retouch' => 6000, 'Micro shading' => 12000 ], 'attr' => 'Service',
		'included' => [ 'Consultation, mapping and shade choice', 'Numbing', 'Micro shading or retouch', 'Aftercare instructions' ],
		'specs' => [ 'Time' => 'About 2–3 hours', 'Deposit' => 'Booked with an M-Pesa deposit', 'Aftercare' => 'Keep brows dry for about a week' ],
		'upsells' => [ 'brow-care' ],
	],
	// Makeup.
	[
		'slug' => 'makeup', 'name' => 'Makeup', 'cats' => [ 'makeup' ],
		'badge' => 'Makeup', 'perk' => 'Camera-ready glam', 'perks' => 'Everyday to full glam | Special occasions | Bridal', 'time' => '45–90 min',
		'short' => 'Makeup application for any day, full glam, special occasions and bridal makeup that lasts from the vows to the last dance.',
		'desc' => 'Tell us the occasion and the look and we will make it happen: soft and natural, bold full glam, or bridal makeup that photographs beautifully and lasts all day. Lashes on request. For bridal parties, book on WhatsApp so we can bring in more artists.',
		'options' => [ 'Makeup application' => 1500, 'Full glam' => 2500, 'Special occasion' => 3500, 'Bridal makeup' => 4000 ], 'attr' => 'Look',
		'included' => [ 'Skin prep', 'Foundation matched to your skin', 'Eyes, brows and lips', 'Setting for long wear' ],
		'specs' => [ 'Time' => 'About 45–90 minutes', 'Bridal' => 'Booked with a deposit; trials on request', 'Bridal party' => 'Book on WhatsApp' ],
		'upsells' => [ 'brow-care', 'classic-facials' ],
	],
	// Signature glow packages: their own bundles.
	[
		'slug' => 'glow-trio', 'name' => 'Glow Trio: Pedicure, Manicure & Facial', 'cats' => [ 'packages' ],
		'badge' => 'Best value', 'perk' => 'Head-to-toe glow', 'perks' => 'Pedicure | Manicure | Facial', 'time' => 'About 2½ hours',
		'short' => 'The head-to-toe classic: a pedicure, a manicure and a facial in one visit.',
		'desc' => 'Pamper yourself from head to toe. Your feet soak and get the full pedicure, your hands get a manicure, and you finish with a facial in our facial room.',
		'price' => 2500,
		'included' => [ 'Pedicure', 'Manicure', 'Facial' ],
		'specs' => [ 'Time' => 'About 2½ hours', 'Upgrade' => 'Ask for gel on hands or feet', 'Payment' => 'After your treatment, M-Pesa or cash' ],
		'upsells' => [ 'brow-care' ],
	],
	[
		'slug' => 'massage-facial-manicure', 'name' => 'Unwind: Massage, Facial & Manicure', 'cats' => [ 'packages' ],
		'badge' => 'Pamper day', 'perk' => 'Relax, refresh, polish', 'perks' => 'Massage | Facial | Manicure', 'time' => 'About 3 hours',
		'short' => 'A massage to melt the tension, a facial for fresh skin, and a manicure to finish you off.',
		'desc' => 'Our weekend pampering bundle: unwind in the massage room, move to the facial room for a refreshing facial, and finish at the manicure bar with clean, polished hands.',
		'price' => 3500,
		'included' => [ 'Massage', 'Facial', 'Manicure' ],
		'specs' => [ 'Time' => 'About 3 hours', 'Payment' => 'After your treatment, M-Pesa or cash' ],
		'upsells' => [ 'pedicure' ],
	],
	[
		'slug' => 'aromatherapy-facial', 'name' => 'Aromatherapy Massage & Facial', 'cats' => [ 'packages' ],
		'badge' => 'Calm', 'perk' => 'Essential-oil escape', 'perks' => 'Aromatherapy massage | Facial | Total calm', 'time' => 'About 2 hours',
		'short' => 'An aromatherapy massage with calming essential oils, then a facial. Pure relaxation.',
		'desc' => 'Breathe in, switch off. A full aromatherapy massage with essential oils, followed by a facial that leaves your skin as calm as you feel.',
		'price' => 3500,
		'included' => [ 'Aromatherapy massage', 'Facial' ],
		'specs' => [ 'Time' => 'About 2 hours', 'Payment' => 'After your treatment, M-Pesa or cash' ],
		'upsells' => [ 'steam-sauna' ],
	],
	[
		'slug' => 'scrub-steam-massage', 'name' => 'Body Scrub, Steam & Massage', 'cats' => [ 'packages' ],
		'badge' => 'Body ritual', 'perk' => 'Polished & relaxed', 'perks' => '30 min steam | Body scrub | Massage', 'time' => 'About 2½ hours',
		'short' => 'Thirty minutes of steam, a full-body scrub and a massage: the complete body reset.',
		'desc' => 'The steam opens your pores, the scrub polishes your skin, and the massage melts away the tension. You leave glowing and completely relaxed.',
		'price' => 4000,
		'included' => [ '30 minutes of steam', 'Full-body scrub', 'Massage', 'Robe, towels and shower' ],
		'specs' => [ 'Time' => 'About 2½ hours', 'Payment' => 'After your treatment, M-Pesa or cash' ],
		'upsells' => [ 'hydrafacial' ],
	],
	[
		'slug' => 'jelly-soak-deep-cleanse', 'name' => 'Jelly Soak Pedicure & Deep Cleansing Facial', 'cats' => [ 'packages' ],
		'badge' => 'Treat', 'perk' => 'Soft feet, clear skin', 'perks' => 'Jelly soak pedicure | Deep cleansing facial', 'time' => 'About 2 hours',
		'short' => 'Our jelly soak pedicure with a deep cleansing facial.',
		'desc' => 'Your feet sink into the warm lavender jelly soak and get the full pedicure, then a deep cleansing facial clears and refreshes your skin.',
		'price' => 3000,
		'included' => [ 'Jelly soak pedicure', 'Deep cleansing facial' ],
		'specs' => [ 'Time' => 'About 2 hours', 'Payment' => 'After your treatment, M-Pesa or cash' ],
		'upsells' => [ 'manicure' ],
	],
	[
		'slug' => 'wax-pedicure-combos', 'name' => 'Wax & Pedicure Combos', 'cats' => [ 'packages' ],
		'badge' => 'Combo', 'perk' => 'Smooth from top to toe', 'perks' => 'Waxing | Pedicure | Facial option', 'time' => 'About 2 hours',
		'short' => 'Two smooth-skin combos: a facial, underarm wax and pedicure, or a Brazilian, half-leg wax and pedicure.',
		'desc' => 'Get everything done in one visit. Choose the facial, underarm wax and pedicure combo, or the Brazilian, half-leg wax and pedicure combo.',
		'options' => [ 'Brazilian, half leg & pedicure' => 3200, 'Facial, underarm wax & pedicure' => 3300 ], 'attr' => 'Combo',
		'included' => [ 'Waxing in a private room', 'Pedicure', 'Facial (facial combo)' ],
		'specs' => [ 'Time' => 'About 2 hours', 'Payment' => 'After your treatment, M-Pesa or cash' ],
		'upsells' => [ 'brow-care' ],
	],
	[
		'slug' => 'couples-side-by-side', 'name' => 'Couples & Besties: Side by Side', 'cats' => [ 'packages' ],
		'badge' => 'For two', 'perk' => 'Better together', 'perks' => 'Side-by-side chairs | Massage for two | Steam or sauna', 'time' => '1–2 hours',
		'short' => 'Treatments for two, side by side: manicure and pedicure, body scrub with steam or sauna, or an hour of aromatherapy massage plus pedicure each.',
		'desc' => 'Bring your partner, your best friend or your mum. You sit side by side in our pedicure chairs, share the steam, or relax in the massage room together. Prices are for two people.',
		'options' => [ 'Side-by-side manicure & pedicure (for two)' => 3200, 'Steam or sauna + side-by-side body scrub (for two)' => 4000, '1-hr aromatherapy massage + pedicure (for two)' => 7600 ], 'attr' => 'Package',
		'included' => [ 'Treatments for two people', 'Side-by-side chairs or rooms', 'Robes and towels' ],
		'specs' => [ 'Time' => 'About 1–2 hours', 'Price' => 'For two people', 'Payment' => 'After your treatment, M-Pesa or cash' ],
		'upsells' => [ 'steam-sauna' ],
	],
	[
		'slug' => 'bridal-glow', 'name' => 'Bridal Glow', 'cats' => [ 'packages', 'makeup' ],
		'badge' => 'For brides', 'perk' => 'Glow down the aisle', 'perks' => 'Full facial | Gel mani & pedi | Bridal makeup', 'time' => 'Over 1–2 visits',
		'short' => 'Everything the bride needs to glow: a full facial, a gel manicure and pedicure, and bridal makeup on the day.',
		'desc' => 'Plan your glow. We suggest the full facial and gel manicure and pedicure a few days before the wedding, and the bridal makeup on the morning itself. The price adds up our list prices for the four treatments. For the bridal party and the mothers, WhatsApp us and we will plan the day together.',
		'price' => 8300,
		'included' => [ 'Full facial (KSh 1,500)', 'Manicure with gel (KSh 1,300)', 'Pedicure with gel (KSh 1,500)', 'Bridal makeup on the day (KSh 4,000)' ],
		'specs' => [ 'Time' => 'Over 1–2 visits', 'Deposit' => 'Booked with an M-Pesa deposit', 'Bridal party' => 'Plan it with us on WhatsApp' ],
		'upsells' => [ 'brow-care', 'hydrafacial' ],
	],
	// Gift vouchers.
	[
		'slug' => 'gift-voucher', 'name' => 'Aura Glow Gift Voucher', 'cats' => [ 'gift-vouchers' ],
		'badge' => 'Gift', 'perk' => 'Give the glow', 'perks' => 'Any treatment | Sent on WhatsApp | Valid 6 months', 'time' => 'Use within 6 months',
		'short' => 'A voucher for any treatment at Aura Glow, for birthdays, anniversaries, bridal showers or just because. We send it on WhatsApp with your message.',
		'desc' => 'Choose the amount, tell us who it is for and add a message. We send a digital voucher with its code on WhatsApp, to you or straight to them. They can spend it on any treatment or package.',
		'options' => [ 'KSh 1,000' => 1000, 'KSh 2,500' => 2500, 'KSh 5,000' => 5000, 'KSh 7,500' => 7500, 'KSh 10,000' => 10000 ], 'attr' => 'Amount',
		'included' => [ 'Digital voucher with a unique code', 'Your personal message', 'Sent on WhatsApp to you or to them' ],
		'specs' => [ 'Valid' => '6 months from purchase', 'Use on' => 'Any treatment or package', 'Payment' => 'M-Pesa before we send it' ],
		'upsells' => [ 'glow-trio', 'hydrafacial' ],
	],
];

function ag_attach_images( $pid, $slug, $name ) {
	$num   = fn( $f ) => preg_match( '#^' . preg_quote( $slug, '#' ) . '(?:-(\d+))?\.webp$#', basename( $f ), $m ) ? (int) ( $m[1] ?? 1 ) : 0;
	$files = array_values( array_filter( glob( "/wordpress/ag-images/$slug*.webp" ) ?: [], fn( $f ) => $num( $f ) > 0 ) );
	usort( $files, fn( $a, $b ) => $num( $a ) <=> $num( $b ) );
	$ids = [];
	foreach ( $files as $file ) {
		$base = basename( $file );
		$tmp  = wp_tempnam( $base );
		copy( $file, $tmp );
		$n   = $num( $file );
		$att = ag_sideload( $tmp, $base, $pid, $name . ( $n > 1 ? ' – photo ' . $n : '' ) );
		if ( ! is_wp_error( $att ) ) $ids[ $n ] = $att;
	}
	return $ids; // photo number => attachment id
}

$ids = [];
foreach ( $products as $order => $d ) {
	$variable = ! empty( $d['options'] );
	$p = $variable ? new WC_Product_Variable() : new WC_Product_Simple();
	$p->set_name( $d['name'] );
	$p->set_slug( $d['slug'] );
	$p->set_status( 'publish' );
	$p->set_menu_order( $order );
	$p->set_virtual( true );
	$p->set_description( $d['desc'] );
	$p->set_short_description( $d['short'] );
	$p->set_category_ids( array_map( fn( $c ) => $cat[ $c ], $d['cats'] ) );
	// The home page's "Signature glow packages" section shows the featured treatments: the packages.
	$p->set_featured( in_array( 'packages', $d['cats'], true ) );
	foreach ( [ 'badge', 'perk', 'perks', 'time', 'clip' ] as $k ) {
		if ( ! empty( $d[ $k ] ) ) $p->update_meta_data( '_ag_' . $k, $d[ $k ] );
	}
	$p->update_meta_data( '_ag_specs', implode( "\n", array_map( fn( $k, $v ) => "$k: $v", array_keys( $d['specs'] ), $d['specs'] ) ) );
	$p->update_meta_data( '_ag_included', implode( "\n", $d['included'] ) );
	if ( ! empty( $d['quote'] ) ) $p->update_meta_data( '_ag_quote', 'yes' );

	if ( $variable ) {
		$a = new WC_Product_Attribute();
		$a->set_name( $d['attr'] );
		$a->set_options( array_keys( $d['options'] ) );
		$a->set_position( 0 );
		$a->set_visible( true );
		$a->set_variation( true );
		$p->set_attributes( [ $a ] );
		$p->set_default_attributes( [ sanitize_title( $d['attr'] ) => array_key_first( $d['options'] ) ] );
	} else {
		$p->set_regular_price( $d['price'] );
	}
	$pid = $p->save();
	$ids[ $d['slug'] ] = $pid;

	$imgs = ag_attach_images( $pid, $d['slug'], $d['name'] );
	if ( $imgs ) {
		// Written as meta: a second full product save here slowed the import.
		set_post_thumbnail( $pid, $imgs[1] ?? reset( $imgs ) );
		update_post_meta( $pid, '_product_image_gallery', implode( ',', array_values( array_diff_key( $imgs, [ 1 => true ] ) ) ) );
	}

	if ( $variable ) {
		$n = 0;
		foreach ( $d['options'] as $label => $price ) {
			$v = new WC_Product_Variation();
			$v->set_parent_id( $pid );
			$v->set_attributes( [ sanitize_title( $d['attr'] ) => $label ] );
			$v->set_virtual( true );
			$v->set_regular_price( (string) $price );
			$v->set_menu_order( $n++ );
			$v->save();
		}
		WC_Product_Variable::sync( $pid );
	}
}

// "Pairs well with" on each service page.
foreach ( $products as $d ) {
	if ( ! empty( $d['upsells'] ) ) {
		update_post_meta( $ids[ $d['slug'] ], '_upsell_ids', array_values( array_filter( array_map( fn( $s ) => $ids[ $s ] ?? 0, $d['upsells'] ) ) ) );
	}
}

// Pages.
function ag_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) return $existing->ID;
	return wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content ] );
}
ag_page( 'book', 'Book your glow session', "<!-- wp:paragraph -->\n<p>Choose your treatments and any extras, pick a day and a time, and see your total as you go. Reserve it here, or send the summary to our WhatsApp and we confirm your slot.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[ag_booking]\n<!-- /wp:shortcode -->" );
ag_page( 'price-list', 'Services & price list', "<!-- wp:paragraph -->\n<p>Every treatment at Aura Glow with its price and typical time. Tap Book on anything to add it to your appointment.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[ag_price_list]\n<!-- /wp:shortcode -->" );
ag_page( 'gift-vouchers', 'Gift vouchers', "<!-- wp:paragraph -->\n<p>Give someone the glow: a voucher for any treatment, for birthdays, anniversaries, bridal showers or just because. We send it on WhatsApp with your message.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[ag_voucher]\n<!-- /wp:shortcode -->" );

// Cart and checkout read as an appointment.
foreach ( [ 'shop' => 'All treatments', 'cart' => 'Your appointment', 'checkout' => 'Confirm your appointment' ] as $page => $title ) {
	$id = wc_get_page_id( $page );
	if ( $id > 0 ) wp_update_post( [ 'ID' => $id, 'post_title' => $title ] );
}

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();

// Skip WooCommerce's first-run redirect and setup checklist so the admin opens on the store itself.
delete_transient( '_wc_activation_redirect' );
update_option( 'woocommerce_task_list_hidden_lists', [ 'setup', 'extended' ] );
update_option( 'woocommerce_task_list_complete', 'yes' );
update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
update_option( 'woocommerce_admin_install_timestamp', time() - WEEK_IN_SECONDS );

$wpdb->query( 'COMMIT' );
wp_suspend_cache_invalidation( false );
wp_defer_term_counting( false );
