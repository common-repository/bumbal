(function( $ ) {
	'use strict';

	$('#bumbal-timeslot-form').on("submit", function (e) {
		e.preventDefault();
		var bumbalForm = $(this);
		var bumbalData = new FormData(bumbalForm[0]);
	
		$.ajax({
			type: 'post',
			url: bumbal_ajax_object.ajax_url,
			data: bumbalData,
			contentType: false,
			processData: false,
			beforeSend: function() {
				bumbalForm.find('input[type=submit]').prop('disabled', true);
			},
			success: function (response) {
				$('.alert').html(response);
				bumbalForm.slideUp();
			},
			error: function (response) {
				console.error('bb_timeslot:', response);
				bumbalForm.find('input[type=submit]').prop('disabled', false);
				$('.alert').html(response);
			},
		});
		return false;
	});

})( jQuery );