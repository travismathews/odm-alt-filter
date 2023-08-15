document.addEventListener('DOMContentLoaded', function() {
    const altTextInputs = document.querySelectorAll('.odm-alt-text-input');
    
    altTextInputs.forEach(input => {
        input.addEventListener('change', function(event) {
            const postId = event.target.getAttribute('data-post-id');
            const newValue = event.target.value;

            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'save_odm_alt_text',
                    post_id: postId,
                    alt_text: newValue,
                    nonce: odmAltFilter.nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayFeedback(event.target, 'Saved!', 'success');
                } else {
                    displayFeedback(event.target, 'Error saving.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                displayFeedback(event.target, 'Error saving.', 'error');
            });
        });
    });
});

function displayFeedback(element, message, type) {
    const feedback = document.createElement('span');
    feedback.className = `odm-feedback odm-${type}`;
    feedback.textContent = message;
    element.parentNode.appendChild(feedback);

    // Clear the feedback message after 3 seconds.
    setTimeout(() => {
        feedback.remove();
    }, 3000);
}
