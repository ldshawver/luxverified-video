(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {

		/* ===============================
		   REJECTION MODAL
		   =============================== */

		const modal      = document.getElementById('luxvv-reject-modal');
		const notesField = document.getElementById('luxvv-reject-notes');
		const userLabel  = document.getElementById('luxvv-reject-user');
		const cancelBtn  = document.getElementById('luxvv-reject-cancel');
		const confirmBtn = document.getElementById('luxvv-reject-confirm');

		if (!modal) return; // safety

		let rejectUrl = '';

		document.querySelectorAll('.luxvv-reject-btn').forEach(btn => {
			btn.addEventListener('click', () => {
				rejectUrl = btn.dataset.rejectUrl;
				userLabel.textContent = 'Rejecting: ' + btn.dataset.user;
				notesField.value = '';
				modal.setAttribute('aria-hidden', 'false');
			});
		});

		cancelBtn.addEventListener('click', () => {
			modal.setAttribute('aria-hidden', 'true');
		});

		confirmBtn.addEventListener('click', () => {
			const notes = notesField.value.trim();
			if (!notes) {
				alert('Please enter rejection notes.');
				return;
			}
			window.location.href =
				rejectUrl + '&notes=' + encodeURIComponent(notes);
		});
	});
		/* ===============================
		   APPROVE CONFERMATION
		   =============================== */	
	document.querySelectorAll('.luxvv-approve-btn').forEach(btn => {
	btn.addEventListener('click', e => {
		if (!confirm('Approve and verify this user?')) {
			e.preventDefault();
		}
	});
});

})();
