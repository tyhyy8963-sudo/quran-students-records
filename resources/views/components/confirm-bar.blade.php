{{-- شريط تأكيد موحّد يستدعيه JS عبر confirmDialog() قبل أي إجراء هدّام (S3 · #21) --}}
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-card" role="alertdialog" aria-modal="true">
        <p class="confirm-message" id="confirmMessage"></p>
        <div class="confirm-actions">
            <button type="button" class="btn btn-secondary" id="confirmNoBtn">لا</button>
            <button type="button" class="btn btn-primary" id="confirmYesBtn">نعم</button>
        </div>
    </div>
</div>
