(function(window, oemr_voicenote, bootstrap, jQuery) {
	/**
     * @type {string} The path of where the module is installed at.  In a multisite we pull this from the server configuration, otherwise we default here
     */
    let moduleLocation = oemr_voicenote.settings.modulePath || '/interface/modules/custom_modules/oe-module-voicenote/';
    let activenote  = null;

    // This invokes the find-patient popup.
    window.openrecord = function() {
        dlgopen(moduleLocation + 'public/index.php?action=voicenote_popup', '_blank', '800', '', false, '', { sizeHeight : 'full'});
    }

    window.setnotevalue = function(note) {
        if(top.activenote && note != "") {
            top.activenote.value = top.activenote.value + note;
        }
    }

    window.getnoteelement = function() {
        return top.activenote ? top.activenote : false
    }

    var targetNode = document.querySelector('.frameDisplay', document.getElementById("frameDisplay"));
    const waitForElement = async (selector, rootElement = document.documentElement) => {
        return new Promise((resolve) => {
            const observer = new MutationObserver(() => {
                const element = document.querySelectorAll(selector);

                if (element) {
                    for (var i = 0; i < element.length; i++) {
                        if(element[i].style.display != 'none'){
                            var iframeEle = element[i].querySelector('iframe');
                            if(iframeEle) {
                                var jiframe = $(iframeEle).contents();
                                jiframe.find(".form-control").click(function(){
                                    activenote = $(this)[0];
                                });
                            }
                        }
                    }
                }
            });
          
            observer.observe(rootElement, {
                attributes: true, childList: true, subtree: true
            });
        });
    };

    $(document).ready(function(){
        // waitForElement(".frameDisplay", document.getElementById("framesDisplay"));

        $(document).on('click', "#recordWBtn", function() {
            if(opener.getnoteelement && opener.getnoteelement() === false) {
                alert("Please Select a Textarea or an Input field");
                return false;
            }

            openrecord();
        });

        $.get(moduleLocation + 'public/index.php?action=voicenote_layout', function(data) {
            $('#mainBox').append(data);
        });
    });

    window.oemr_voicenote = oemr_voicenote;
})(window, window.oemr_voicenote || {}, bootstrap, $, window.dlgopen || function() {});
