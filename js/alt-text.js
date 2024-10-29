
jQuery(document).on('click', '.thumbnail', function($) {
    add_generate_alt_text_button();
});


jQuery(document).on('click', '.edit-attachment-frame .edit-media-header', function(e) {
    add_generate_alt_text_button();
});


jQuery(document).ready(function(){
    setTimeout(function(){
        add_generate_alt_text_button();
    }, 2000); 
});


function add_generate_alt_text_button(){
    var attachment_id = jQuery('.alt_txt_attachment_id').val();
    if (!jQuery('.generate-alt-text').length && attachment_id != '' ) {
        var element = jQuery('#attachment-details-two-column-alt-text').parent().next('.description');
        jQuery('<span class="setting" style="text-align:center;"><p class="help"><button class="button generate-alt-text" style="background-color: #795bef; color: white;" data-attachment-id="">Generate Alt Text</button></p><span>')
                    .insertAfter(element);
        jQuery('.generate-alt-text').attr('data-attachment-id', attachment_id);
    }
}


jQuery(document).on('click', '.generate-alt-text', function($) {
    var button = jQuery(this);
    var attachmentId = button.data('attachment-id');

    button.text('Processing...').attr('disabled', true);
    jQuery.ajax({
        url: altTextAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'alt_text_generate',
            nonce: altTextAjax.nonce,
            attachment_id: attachmentId
        },
        dataType:"JSON",
        success: function(response) {
            console.log(response);
            if (response.success) {
                var altTextInput = jQuery('#attachment-details-two-column-alt-text');
                if (altTextInput.length) {
                    altTextInput.val(response.data.alt_text); // Set the generated alt text
                    altTextInput.trigger('change'); // Trigger change event to refresh UI
                }
                // Update the alt text input with the generated text
                jQuery('input[name="_wp_attachment_image_alt"]').val(response.data.alt_text);
                button.after('<p class="success-message">Alt text generated successfully.</p>');
            } else {
                button.after('<p class="error-message">Error: ' + response.data.message + '</p>');
            }
        },
        error: function() {
            button.after('<p class="error-message">An error occurred.</p>');
        },
        complete: function() {
            button.text('Generate Alt Text').removeAttr('disabled');
        }
    });
});
