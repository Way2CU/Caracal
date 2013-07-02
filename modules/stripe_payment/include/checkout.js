function Stripe_handleResponse(status, response) {
	// hide overlay
	$('div#checkout > div.overlay')
		.animate({opacity: 0}, 600, function() {
			$(this).css('display', 'none');
		});

	// handle response
	switch (status) {
		case 200:
			break;

		default:
			break;
	}
	console.log(status, response);
}

function Stripe_handleCheckout(event) {
	// prevent form from submitting
	event.preventDefault();

	// show overlay
	$('div#checkout > div.overlay')
			.css({
				display: 'block',
				opacity: 0
			})
			.animate({opacity: 1}, 600);

	// make new Stripe payment
	Stripe.setPublishableKey('%stripe-key%');
	Stripe.card.createToken({
		number: '%cc-number%',
		cvc: '%cc-cvv%',
		exp_month: '%cc-exp-month%',
		exp_year: '%cc-exp-year%'
	}, Stripe_handleResponse);
}

$(function() {
	// handle clicking on checkout button
	var checkout_form = $('div#checkout form');
	checkout_form.submit(Stripe_handleCheckout)
});
