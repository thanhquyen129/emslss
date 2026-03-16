<script src="https://unpkg.com/html5-qrcode"></script>

<div id="reader" style="width:100%"></div>

<input id="barcode" class="form-control mt-3">

<script>
function onScanSuccess(decodedText){
 document.getElementById('barcode').value = decodedText;

 $.post('../api/assign_ajax.php',{
   barcode:decodedText
 });
}

new Html5Qrcode("reader").start(
 { facingMode:"environment" },
 { fps:10, qrbox:250 },
 onScanSuccess
);
</script>