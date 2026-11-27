document.addEventListener(
	  'click',
	   (e) => {
	    if (e.target.matches('#ms-migration button')) {
				fetch(ms_handle_dismiss.ajaxUrl, {
	        method: 'POST',
	        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
	        body: new URLSearchParams({ action: 'ms_handle_dismiss', nonce: ms_handle_dismiss.nonce }).toString(),
	      });
				console.log('ms_handle_dismiss')
	    }
	  },
	  false
	);
