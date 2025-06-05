// assets/js/script.js
document.addEventListener('DOMContentLoaded', function() {
    // Function to handle quiz sharing
    window.shareQuiz = async function(title, url, buttonElement) {
        const shareData = {
            title: title,
            text: `"${title}" কুইজটিতে অংশগ্রহণ করুন এবং আপনার জ্ঞান যাচাই করুন!`,
            url: url
        };

        const originalButtonInnerHTML = buttonElement.innerHTML;
        const originalButtonClasses = buttonElement.className;
        let successClass = 'btn-success'; 
        
        try {
            if (navigator.share) {
                await navigator.share(shareData);
                // console.log('Quiz shared successfully');
            } else {
                // Fallback: Copy to clipboard
                await navigator.clipboard.writeText(url);
                // console.log('Link copied to clipboard');
                
                buttonElement.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-lg me-1" viewBox="0 0 16 16">
                      <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425z"/>
                    </svg>
                    কপি হয়েছে!`;
                
                buttonElement.className = originalButtonClasses.replace(/btn-outline-\w+-?\w*/g, '').replace(/btn-\w+-?\w*/g, ''); 
                buttonElement.classList.add('btn', successClass); // Ensure 'btn' class is present
                if (originalButtonClasses.includes('btn-sm')) { // Re-add btn-sm if it was there
                    buttonElement.classList.add('btn-sm');
                }


                setTimeout(() => {
                    buttonElement.innerHTML = originalButtonInnerHTML;
                    buttonElement.className = originalButtonClasses; 
                }, 2500);
            }
        } catch (err) {
            console.error('Error sharing quiz or copying link:', err);
            // Fallback alert removed as per user request
        }
    }
});

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/service-worker.js') // নিশ্চিত করুন পাথটি সঠিক
      .then(registration => {
        console.log('ServiceWorker registration successful with scope: ', registration.scope);
      })
      .catch(err => {
        console.log('ServiceWorker registration failed: ', err);
      });
  });
}