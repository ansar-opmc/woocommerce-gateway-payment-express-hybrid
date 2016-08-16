jQuery(document).ready(function(){
	jQuery('#ExMnth').empty();
	var d = new Date();
	n = parseInt( d.getMonth()) + 1;
	for(i=n;i<13;i++){
		if(i<10) 	jQuery('#ExMnth').append('<option value=0'+i+'>0'+i+'</option>');
		else 		jQuery('#ExMnth').append('<option value='+i+'>'+i+'</option>');
	}
	y = d.getFullYear() - 2000;
	jQuery('#ExYear option[value="'+y+'"]').prop('selected', true);

	jQuery("#ExYear").change(function() {
		jQuery('#ExMnth').empty();
		
		if(jQuery("#ExYear").val() == y) ii = n;
		else						 	 ii = 1;
		for(i=ii;i<13;i++){
			if(i<10)
				jQuery('#ExMnth').append('<option value=0'+i+'>0'+i+'</option>');
			else
				jQuery('#ExMnth').append('<option value='+i+'>'+i+'</option>');
		}
	});
	jQuery('#ExMnth option:eq('+n+')').prop('selected', true);

	jQuery("#ccDetail").click(function() {
		var tt = jQuery('input[name="saveCreditDetails"]').val();
		if(tt == "YES")
			jQuery('input[name="saveCreditDetails"]').val("NO");
		else
			jQuery('input[name="saveCreditDetails"]').val("YES");
	});
	jQuery("#ccDetailNew").click(function() {
		var tt = jQuery('input[name="overwriteCreditDetails"]').val();
		if(tt == "YES"){
			jQuery('input[name="overwriteCreditDetails"]').val("NO");
			jQuery("#ccNameRow").hide("slow");
			jQuery("#ccNumberRow").hide("slow");
			jQuery("#expiryDateRow").hide("slow");
		}else{
			jQuery('input[name="overwriteCreditDetails"]').val("YES");
			jQuery("#ccNameRow").show("slow");
			jQuery("#ccNumberRow").show("slow");
			jQuery("#expiryDateRow").show("slow");
		}
	});
	jQuery("#submit_Payment_Express_payment_form").click(function() { // bind click event to link
		jQuery("body").block({
			message: '<img src="'+ pxpost_vars.woo_url + '/assets/images/ajax-loader@2x.gif" alt="wait.." style="float:left; margin-right: 10px;" />'+pxpost_vars.thank_you_message,
			overlayCSS:
			{
				background: "#fff",
				opacity: 0.6
			},
			css: {
				padding:        20,
				textAlign:      "center",
				color:          "#555",
				border:         "3px solid #aaa",
				backgroundColor:"#fff",
				cursor:         "wait",
				lineHeight:		"32px"
			}
		});
		var ajaxurl = pxpost_vars.admin_url;
		var data = {
			action: 			'requestProcess',
			orderNo:			jQuery('input[name="txndata1"]').val(),
			name:				jQuery('input[name="CardName"]').val(),
			ccnum:				jQuery('input[name="CardNum"]').val(),
			ccmm:				jQuery('select[name="ExMnth"]').val(),
			ccyy:				jQuery('select[name="ExYear"]').val(),
			cvcnum:				jQuery('input[name="CVCNum"]').val(),
			merchRef:			jQuery('input[name="MerchRef"]').val(),
			saveCreditDetails:	jQuery('input[name="saveCreditDetails"]').val(),
			overwriteCreditDetails:	jQuery('input[name="overwriteCreditDetails"]').val(),
			billingDetailsSaved:jQuery('input[name="billingDetailsSaved"]').val(),
		};
		jQuery.post(ajaxurl, data, function(response) {
// alert( response );
			var responseURL = "";
			if(response=="APPROVED"){
				responseURL = jQuery('input[name="successURL"]').val();
			}else{
				alert( pxpost_vars.sorry_message + " - " + response );
				responseURL = "";	//jQuery('input[name="failURL"]').val();
			}
			jQuery("body").unblock();
			// jQuery('form[name="checkout"]').hide();
			window.location.href = responseURL;
		});
		return false;
	});
});
