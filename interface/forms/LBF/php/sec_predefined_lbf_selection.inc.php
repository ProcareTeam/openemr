<!-- OEMR - LBF Selector -->
<div class="sec_predefined_lbf_selector_container_<?php echo attr($group_levels); ?> d-inline-flex flex-row flex-nowrap float-right mr-3 mb-1">
    <select id="predefined_lbf_selector_<?php echo attr($group_levels); ?>" data-grouplevel="<?php echo attr($group_levels); ?>" class="sec_predefined_lbf_selector form-control form-control-sm" onChange="selectorChange(this, '<?php echo attr($group_levels); ?>')">
        <option value=""><?php echo xlt("Predefined Selections"); ?></option>
        <option value="add_new"><?php echo xlt("Add New"); ?></option>
    </select>
    <div class="sec_predefined_lbf_selector_actions ml-1"></div>
</div>