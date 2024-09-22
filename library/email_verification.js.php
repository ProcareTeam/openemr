<script>
	/* Email verification */

    // Init Email Verification Elements ("options.inc.php")
    function setEmailVerificationData(val, ele) {
        if(val.trim() == "") {
            ele.querySelector('button.btn_verify_email').disabled = true;
            //$('#DEM #hidden_verification_status').addClass('disabledItem');
        } else {
            ele.querySelector('button.btn_verify_email').disabled = false;
            //$('#DEM #hidden_verification_status').removeClass('disabledItem');
        }
    }

    function setupEmailVerificationElement(ele) {
        var emvElementor = ele;
        if(emvElementor && emvElementor != null) {
            let initStatus = emvElementor.dataset.initstatus;
            let inputElement = emvElementor.querySelector('input[type="text"]');

            setEmailVerificationData(inputElement.value, ele);

            if(initStatus == "1") {
            	setEmailVerificationStatusValue(true, ele);
            } else {
            	setEmailVerificationStatusValue(false, ele);
            }

        }
    }

    // Set Status Value
    function setEmailVerificationStatusValue(status, ele) {
        if(status == true) {
            ele.querySelector('.status-icon-container').innerHTML  = "<i class='fa fa-check-circle email-verification-icon-successful' aria-hidden='true'></i>";
            ele.querySelector('.hidden_verification_status').value = "1";
        } else if(status == false) {
            ele.querySelector('.status-icon-container').innerHTML  = "<i class='fa fa-times-circle email-verification-icon-failed' aria-hidden='true'></i>";
            ele.querySelector('.hidden_verification_status').value = "0";
        }
    }

    // Set Button Status
    function emvSetLoadingValue(status, ele) {
        if(status == true) {
            $(ele).find('.btn_verify_email').attr("disabled", "disabled");
        } else if(status == false) {
            $(ele).find('.btn_verify_email').removeAttr("disabled", "disabled");
        }
    }

    // Email Verification Service ("options.inc.php")
    async function callEmailVerificationService(val) {
        let result;
        let ajaxurl = top.webroot_url + '/interface/email_verification/ajax_email_verification.php?email='+val;

        if(val && val != "") {
            try {
                result = await $.ajax({
                    url: ajaxurl,
                    type: 'GET',
                    timeout: 30000
                });
                return JSON.parse(result);
            } catch (error) {
                if(error.statusText == "timeout") {
                    alert('Request Timeout');
                } else {
                    alert('Something went wrong');
                }
            }
        }
        return null;
    }

    // Handle email verification ("options.inc.php")
    async function handleEmailVerification(val, ele) {
        emvSetLoadingValue(true, ele);
        var reponceData = await callEmailVerificationService(val);
        emvSetLoadingValue(false, ele);

        if(reponceData != null) {
            var reponce = reponceData;
            if(reponce.success == "true") {
                if(reponce.result == "valid" && reponce.disposable == "false" && reponce.accept_all == "false") {
                    setEmailVerificationStatusValue(true, ele);
                } else {
                    setEmailVerificationStatusValue(false, ele);
                }
            } else if(reponce.success == "false"){
                alert(reponce.message);
            }
        }
    }

    $(document).ready(function(){
        document.querySelectorAll('.emv-input-group-container').forEach(function(container) {
            setupEmailVerificationElement(container);
        });

        // On value change enable/disable verification element.
        $('.emv-input-group-container input[type="text"]').keyup(function() {
            setEmailVerificationData($(this).val(), $(this).parent().parent()[0]);
        });

        // On change check email validation ("options.inc.php")
        $('.emv-input-group-container').on('input', 'input[type="text"]', function() {
            let inputVal = $(this).val();
            let emvContainer = $(this).parent().parent()[0];
            let initVStatus = emvContainer.dataset.initstatus;
            let initVEmail = emvContainer.dataset.initemail;

            if(inputVal == initVEmail && initVStatus == '1') {
                setEmailVerificationStatusValue(true, emvContainer);
            } else {
                setEmailVerificationStatusValue(false, emvContainer);
            }
        });

        //On click check email validation ("options.inc.php")
        $('.emv-input-group-container').on('click', 'button.btn_verify_email', async function() {
            let emvContainer = $(this).parent().parent().parent()[0];
            let initid = emvContainer.dataset.id;

            if(initid == "") return;

            let isDisable = $(this).is(':disabled');
            let inputVal = $('#' + initid).val();
            let innerHtmlVal = $(this).html();

            //Set loader
            $(this).html('<div class="spinner-border btn-loader"></div>');

            if(isDisable == false) {
                await handleEmailVerification(inputVal, emvContainer);
            }

            //Unset loader
            $(this).html(innerHtmlVal);
        });
    });
</script>