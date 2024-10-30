jQuery(document).ready( function() {
	var j = jQuery;
	
	j(".topic-subscribe > a").click( function() {
		it = j(this);
		var theid = j(this).attr('id');
		var stheid = theid.split('-');
		
		j('.pagination .ajax-loader').toggle();
		
		var data = {
			action: 'ass_ajax',
			a: stheid[0],
			topic_id: stheid[1],
			_ajax_nonce: stheid[2],
		};
				
		j.post(ajaxurl, data, function(response) {
			if ( response == 'subscribe' ) {
				var m = 'Unsubscribe';
				theid = theid.replace( 'subscribe', 'unsubscribe' );
				j(it).removeClass('unsubscribed');
				j(it).addClass('subscribed');
			} else if ( response == 'unsubscribe' ) {
				var m = 'Subscribe';
				theid = theid.replace( 'unsubscribe', 'subscribe' );
				j(it).removeClass('subscribed');
				j(it).addClass('unsubscribed');
			} else
				var m = 'Error';
					
			j(it).html(m);
			j(it).attr('id', theid);
			
			j('.pagination .ajax-loader').toggle();
			
		});
	});
});