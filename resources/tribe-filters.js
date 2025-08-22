jQuery(document).ready(function($) {

	var form = $('#the-filters'),
		saved= $('#tribe-saved-filters' ),
	inactive = $('#tribe-filters-inactive'),
	active = $('#tribe-filters-active');

	inactive.change(function() {
		var picked = $(this).find(':selected'),
		val = picked.val(),
		html = Tribe_Filters.template[val];
		if ( val == '0' ) // ignore the pseudo-header
			return false;
		$(html).css({opacity:0}).appendTo(active).animate({opacity:1});
		$('#tribe-filters-active').find('select.multi-active:visible').select2();
		picked.remove();
	});

	active.delegate('.close', 'click', function(){
		var clicked = $(this),
			row = clicked.closest('tr'),
			val = row.find('input:last, select').attr('name');

		val = cleanUpKey(val);
		inactive.append(Tribe_Filters.option[val]);
		row.fadeRemove()
	});

	$('#tribe-filters-active select.multi-active')
		.each( function() {
			$(this).select2();
		});

	if(saved.length && saved.find( 'option' ).length > 2){
		$( 'h2.select-saved-filter span' ).show();
	}

	function cleanUpKey(val) {
        if ( ! val ) {
            return val;
        }

		return val
			.replace(Tribe_Filters.valPrefix, '')
			.replace(Tribe_Filters.prefix, '')
			.replace(/\[\]$/, '');
	}

	// toggle single- or multi-select
	active.delegate('.multi-toggle', 'click', function(event) {
		var me = $(this),
		select = me.prev(),
		name = select.attr('name');

        if ( ! me || ! select || ! name ) {
            return;
        }

		if ( me.hasClass('on') ) {
			select.attr('multiple', 'multiple').attr('name', name + '[]');
			me.hide();
			select.select2();
		} else {
			select.removeAttr('multiple').attr('name', name.replace(/\[\]$/, ''));
			me.text('+');
		}
		select.toggleClass('multi-active');
		me.toggleClass('on');

	});

	// view a saved filter
	$('#tribe-saved-filters').change(function(){
		var me = $(this),
		id = me.val(),
		url = me.attr('data:submit_url') + id;

		if ( id > 0 ) {
            window.location = url;
        }
	});

	// clicking on page numbers - need to save the goodness
	$('#posts-filter .tablenav-pages').delegate('a', 'click', function(ev) {
		ev.preventDefault();
		var base_url = $(this).attr('href');

		form.attr('action', base_url).submit();
	});

	// Update Filters
	form.find('input[name=tribe-update-saved-filter]').click(function() {
		// change to current URL to keep the saved_filter bit.
		form.attr('action', window.location.href);
	});

	// add the # of posts found, if applicable
    if ( $('.tablenav-pages').length === 0 ) {
		$('<div class="tablenav-pages"><span class="displaying-num">'+ Tribe_Filters.displaying +'</span></div>').prependTo('.tablenav');
	}

	// Datepicker
	active.delegate('.tribe-datepicker', 'focusin', function(event) {
		$(this).datepicker({
			dateFormat: 'yy-mm-dd',
			changeYear: true,
			changeMonth: true,
			numberOfMonths: 2
		});
	});

});

(function($){
	$.fn.fadeRemove = function(speed) {
		return $(this).animate({opacity: 0}, speed, function() {
		  $(this).remove();
		});
	};
})(jQuery);
