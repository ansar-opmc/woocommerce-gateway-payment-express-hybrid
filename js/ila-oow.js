// jQuery(function() {

	// jQuery("a.refund").on("click", function(e) {
		// var link = this;
		// e.preventDefault();
		// jQuery("<div>Are you sure you want to continue?</div>").dialog({
			// buttons: {
				// "Ok": function() {
					// window.location = link.href;
				// },
				// "Cancel": function() {
					// $(this).dialog("close");
				// }
			// }
		// });
	// });
// });
var $j=jQuery.noConflict();

$j(document).ready(function(){

	$j('a.refund').click(function(e){
		var myClasses = this.classList;
		trClass = myClasses[3];
		
		tot = $j('tr.' + trClass + ' > td.order_total').html().replace(/(<([^>]+)>)/ig," ");
		tot = tot.split(" ");	//an array of tokens
        return confirm('Are you sure you wish to refund this transaction for ' + tot[0] + '?');
	});

});