document.addEventListener('DOMContentLoaded', function () {
    var wishlistButton = document.getElementById('wishlist-button');
    if(!wishlistButton){
        return
    }
    wishlistButton.addEventListener('click', function (event) {
        // Prevent the form from submitting immediately
        event.preventDefault();

        // Get the product ID
        var productIdInput = document.querySelector('[name="product_id"]');
        var productId = productIdInput ? productIdInput.value : 0;

        // Send AJAX request to update the wishlist
        updateWishlist(productId);
    });

    function updateWishlist(productId) {
        // Send AJAX request
        var xhr = new XMLHttpRequest();
        xhr.open('POST', wishlist_ajax_object.ajaxurl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 400) {
                // Success, handle the response here
                console.log(xhr.responseText);
                // Change the button text based on the response
                wishlistButton.innerText = xhr.responseText.trim() === 'Added to Wishlist' ? 'In Wishlist' : 'Add to Wishlist';
            } else {
                // Error, handle the error here
                console.error('Error updating wishlist:', xhr.statusText);
            }
        };
        xhr.onerror = function () {
            // Network error, handle it here
            console.error('Network error while updating wishlist.');
        };

        // Prepare data to send
        var data = 'action=update_wishlist&product_id=' + encodeURIComponent(productId);
        xhr.send(data);
    }
});
