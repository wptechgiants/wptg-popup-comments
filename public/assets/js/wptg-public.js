jQuery(function($) {

    /*
     * On comment form submit
     */
    $('body').on('submit', '#commentform', function(e) {
        e.preventDefault();
        // define some vars
        var button = $('body').find('#submit'), // submit button
            respond = $('body').find('#respond'), // comment form container
            commentlist = $('body').find('.comment-list'), // comment list container
            cancelreplylink = $('body').find('#cancel-comment-reply-link');

        // if user is logged in, do not validate author and email fields
        if ($('body').find('#author').length)
            $('body').find('#author').validate();

        if ($('body').find('#email').length)
            $('body').find('#email').validateEmail();

        // validate comment in any case
        $('body').find('#comment').validate();

        // if comment form isn't in process, submit it
        if (!button.hasClass('loadingform') && !$('body').find('#author').hasClass('error') && !$('body').find('#email').hasClass('error') && !$('body').find('#comment').hasClass('error')) {

            // ajax request
            $.ajax({
                type: 'POST',
                url: wptgPopupCommentsPublic.ajaxUrl, // admin-ajax.php URL
                data: $(this).serialize() + '&action=ajaxcomments', // send form data + action parameter
                beforeSend: function(xhr) {
                    // what to do just after the form has been submitted
                    button.addClass('loadingform').val('Loading...');
                },
                error: function(request, status, error) {
                    if (status == 500) {
                        alert('Error while adding comment');
                    } else if (status == 'timeout') {
                        alert('Error: Server doesn\'t respond.');
                    } else {
                        // process WordPress errors
                        var wpErrorHtml = request.responseText.split("<p>"),
                            wpErrorStr = wpErrorHtml[1].split("</p>");

                        alert(wpErrorStr[0]);
                    }
                },
                success: function(addedCommentHTML) {

                    // if this post already has comments
                    if (commentlist.length > 0) {

                        // if in reply to another comment
                        if (respond.parent().hasClass('comment')) {

                            // if the other replies exist
                            if (respond.parent().children('.children').length) {
                                respond.parent().children('.children').append(addedCommentHTML);
                            } else {
                                // if no replies, add <ol class="children">
                                addedCommentHTML = '<ol class="children">' + addedCommentHTML + '</ol>';
                                respond.parent().append(addedCommentHTML);
                            }
                            // close respond form
                            cancelreplylink.trigger("click");
                        } else {
                            // simple comment
                            commentlist.append(addedCommentHTML);
                        }
                    } else {
                        // if no comments yet
                        addedCommentHTML = '<ol class="comment-list">' + addedCommentHTML + '</ol>';
                        respond.before($(addedCommentHTML));
                    }
                    // clear textarea field
                    $('body').find('#comment').val('');
                },
                complete: function() {
                    // what to do after a comment has been added
                    button.removeClass('loadingform').val('Post Comment');
                }
            });
        }
        return false;
    });

    $('body').on('click', '.wptg_show_comments', function(e) {
        e.preventDefault();
        var $discussion_wrapper = $('body .wptg_popup_wrap');
        
        $.ajax({
            method: 'POST',
            url: wptgPopupCommentsPublic.ajaxUrl,
            data: {
                action: 'wptg_comment_wrapper',
                pid: $(this).data('id')
            },
            success: function (response) {
                $discussion_wrapper.find('.wptg_comments_html').html(response);
            }
        });

    });

    $('body').on('click', '.close_popup', function(e) {
        e.preventDefault();
        $('body').find( '.wptg_comments_html').html('');
    } );

    jQuery.extend(jQuery.fn, {
        /*
         * check if field value lenth more than 3 symbols ( for name and comment ) 
         */
        validate: function () {
            if (jQuery(this).val().length < 3) {jQuery(this).addClass('error');return false} else {jQuery(this).removeClass('error');return true}
        },
        /*
         * check if email is correct
         * add to your CSS the styles of .error field, for example border-color:red;
         */
        validateEmail: function () {
            var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/,
                emailToValidate = jQuery(this).val();
            if (!emailReg.test( emailToValidate ) || emailToValidate == "") {
                jQuery(this).addClass('error');return false
            } else {
                jQuery(this).removeClass('error');return true
            }
        },
    });
});
