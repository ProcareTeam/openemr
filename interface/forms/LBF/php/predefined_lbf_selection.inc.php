<!-- @VH: LBF Selector [V10005] -->
<div class="predefined_lbf_selector_container d-inline-flex flex-row flex-nowrap float-right mr-3">
    <select id="predefined_lbf_selector" name="predefined_lbf_selector" class="predefined_lbf_selector form-control">
        <option value=""><?php echo xlt("Predefined Selections"); ?></option>
        <option value="add_new"><?php echo xlt("Add New"); ?></option>
    </select>
    <div class="predefined_lbf_selector_actions ml-1"></div>
</div>

<script type="text/javascript">
    function handleLoader(show = false) {
        if(show === true) {
            $('body').append('<div id="form_loader" style="top:0;left:0;position: fixed;width: 100%;height: 100%;background-color: rgba(255,255,255,0.5);z-index: 1000;display: grid;justify-content: center;align-items: center;"><div class="spinner-border spinner-border-lg" role="status"><span class="sr-only">Loading...</span></div></div>');
        } else {
            $('#form_loader').remove();
        }
    }

    async function handlePredefinedLBfSelector(action = '', name = '', isGlobal = '') {
        let formData = new FormData(document.querySelector("form.form-inline"));
        formData.append("action", action);
        formData.append("formname", '<?php echo $formname; ?>');
        formData.append("selection_name", name);
        formData.append("is_global", isGlobal);

        if(action != "DELETE" && name == '') {
            return false;
        }

        const result = await $.ajax({
            type: "POST",
            url: "<?php echo $GLOBALS['webroot']; ?>/interface/forms/LBF/ajax/predefined_lbf_selector_ajax.php",
            processData: false,
            contentType: false,
            data: formData
        });

        if(result != '') {
            return JSON.parse(result);
        }
    }

    async function handleGetPredefinedLBfSelector() {
        const result = await $.ajax({
            type: "POST",
            url: "<?php echo $GLOBALS['webroot']; ?>/interface/forms/LBF/ajax/predefined_lbf_selector_ajax.php",
            datatype: "json",
            data: { 'action' : 'get_selector', 'formname' : '<?php echo $formname; ?>' }
        });

        if(result != '') {
            return JSON.parse(result);
        }

        return [];
    }

    async function preparePredefinedLbfSelectorOption(defVal = '') {
        const selectorData = await handleGetPredefinedLBfSelector();

        $("#predefined_lbf_selector option").remove(); 

        $("#predefined_lbf_selector").append($("<option></option>").attr("value", "").text('Predefined Selections'));
        $("#predefined_lbf_selector").append($("<option></option>").attr("value", "add_new").text('Add new'));

        $.each(selectorData, function( ind, item ){
            $("#predefined_lbf_selector").append($("<option></option>").attr("value", item['id']).text(item['title'])); 
        });

        if(defVal != "") $("#predefined_lbf_selector").val(defVal);
        $("#predefined_lbf_selector").change();
    }

    function open_predefined_lbf_selection(id = '') {
        var url = '<?php echo $GLOBALS['webroot']; ?>/interface/forms/LBF/php/predefined_lbf_selection.php?id='+id;
        dlgopen(url, 'predefined_lbf_selector', 500, 200, '', 'Predefined LBF Selection');
    }

    async function setPredefinedLBFSelection(id = '', name = '', isGlobal = '0') {
        if(id == "") {
            lbfSelectionSaveProcess("ADD_NEW", name, isGlobal);
        } else {
            lbfSelectionSaveProcess("UPDATE", name, isGlobal); 
        }    
    }

    async function lbfSelectionSaveProcess(action = '', name = "", isGlobal = "") {
        handleLoader(true);
        let saveRes = await handlePredefinedLBfSelector(action, name, isGlobal);
        let newId = (saveRes['id']) ? saveRes['id'] : "";
        await preparePredefinedLbfSelectorOption(newId);
        handleLoader(false);
    }

    async function lbfselectionHandle(action = '', id = '') {
        if(action == "ADD_NEW") {
            open_predefined_lbf_selection();
        } else if(action == "UPDATE") {
            open_predefined_lbf_selection(id);
        } else if(action == "DELETE") {
            if(confirm('<?php echo xls("Are you sure you want to delete this item?"); ?>')) {
                lbfSelectionSaveProcess("DELETE");
            }
        }
    }

    $(document).ready(async function() {
        await preparePredefinedLbfSelectorOption();

        $('#predefined_lbf_selector').change(async function(){
            var selVal = $(this).val();

            if(selVal === "" || selVal === "add_new") {
                $('.predefined_lbf_selector_container .predefined_lbf_selector_actions').html('');
            }

            if(selVal === "") return false;
            if(selVal === "add_new") {
                lbfselectionHandle("ADD_NEW");
            } else {
                handleLoader(true);

                await fetchExtExam("", "", "<?php echo $pid; ?>", "global", {"id" : selVal,  "formname" : "<?php echo $formname; ?>"});

                $('.predefined_lbf_selector_container .predefined_lbf_selector_actions').html('<button type="button" class="btn btn-primary" onclick="lbfselectionHandle(\'UPDATE\', \''+selVal+'\')"><i class="fa fa-refresh" aria-hidden="true"></i></button> <button type="button" class="btn btn-danger" onclick="lbfselectionHandle(\'DELETE\', \''+selVal+'\')"><i class="fa fa-trash" aria-hidden="true"></i></button>');

                handleLoader(false);
            }
        });
    });
</script>
<!-- End -->