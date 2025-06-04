// assets/js/script.js
document.addEventListener('DOMContentLoaded', function() {
    // Function to handle quiz sharing
    window.shareQuiz = async function(title, url, buttonElement) {
        const shareData = {
            title: title,
            text: `"${title}" কুইজটিতে অংশগ্রহণ করুন এবং আপনার জ্ঞান যাচাই করুন!`,
            url: url
        };

        // Store original button content and classes
        const originalButtonInnerHTML = buttonElement.innerHTML;
        const originalButtonClasses = buttonElement.className;
        const isOutlineButton = originalButtonClasses.includes('btn-outline-');
        let successClass = 'btn-success'; // Default success class
        let originalShareClass = 'btn-outline-secondary'; // Default original class to restore

        // Determine specific original class for restoration
        if (originalButtonClasses.includes('btn-outline-secondary-custom')) {
            originalShareClass = 'btn-outline-secondary-custom';
        } else if (originalButtonClasses.includes('btn-outline-secondary')) {
             originalShareClass = 'btn-outline-secondary';
        }
        // Add other specific outline classes if needed


        try {
            if (navigator.share) {
                await navigator.share(shareData);
                // You could add a brief "Shared!" message if desired, though system UI usually handles this.
            } else {
                // Fallback: Copy to clipboard
                await navigator.clipboard.writeText(url);
                
                buttonElement.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-lg me-1" viewBox="0 0 16 16">
                      <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425z"/>
                    </svg>
                    কপি হয়েছে!`;
                
                // Change button style to indicate success
                buttonElement.className = originalButtonClasses.replace(/btn-outline-\w+-?\w*/g, '').replace(/btn-\w+-?\w*/g, ''); // Remove existing btn classes
                buttonElement.classList.add('btn', successClass);


                setTimeout(() => {
                    buttonElement.innerHTML = originalButtonInnerHTML;
                    buttonElement.className = originalButtonClasses; // Restore all original classes
                }, 2500);
            }
        } catch (err) {
            console.error('Error sharing quiz:', err);
            // Fallback for older browsers or if user denies permission
            alert(`কুইজটি শেয়ার করতে এই লিংকটি কপি করুন: ${url}`);
        }
    }
});