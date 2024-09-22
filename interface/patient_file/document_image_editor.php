<?php

require_once('../globals.php');
require_once("$srcdir/documents.php");
require_once($GLOBALS['fileroot'] . "/controllers/C_Document.class.php");

use OpenEMR\Core\Header;
use DantSu\PHPImageEditor\Image;

$patient_id = isset($_REQUEST['patient_id']) ? $_REQUEST['patient_id'] : "";
$document_id = isset($_REQUEST['document_id']) ? $_REQUEST['document_id'] : "";
$ajax_action = isset($_REQUEST['ajax_action']) ? $_REQUEST['ajax_action'] : "";

if(isset($ajax_action) && !empty($ajax_action)) {
	// Save image action
	if($ajax_action == "save_image") {
		try {

			$imagebase64 = isset($_REQUEST['imagebase64']) ? $_REQUEST['imagebase64'] : "";
			$imagebase64 = preg_replace('#^data:image/[^;]+;base64,#', '', $imagebase64);
			$imagebinaryData = base64_decode($imagebase64);

			if(empty($imagebase64)) {
                throw new \Exception("Incorrect file");
            }

			$tempFilePath = tempnam(sys_get_temp_dir(), 'image_editor');
			file_put_contents($tempFilePath, $imagebinaryData);

			$fileType = mime_content_type($tempFilePath);
			$fsize = filesize($tempFilePath);

			$d = new Document($document_id);
			$fname = $d->name;
			
			$data = updateDocument(
				$document_id,
	            $fname,
	            $fileType,
	            $tempFilePath,
	            '',
	            $fsize,
	            '',
	            '',
	            true
	        );

			if(isset($data) && isset($data['error']) && !empty($data['error'])) {
				throw new \Exception($data['error']);
			}

			echo json_encode(array ('message' => 'Document updated'));

			// Delete temp file
	        unlink($tempFilePath);

		} catch (\Throwable $e) {
	        // Delete temp file
	        unlink($tempFilePath);

	        echo json_encode(array ('error' => $e->getMessage()));
	    }

	    exit();
	} else if($ajax_action == "get_image") {
		try {

			$d = new Document($document_id);
			$b64image = "";
			if(!empty($d) && !empty($d->id) && !empty($d->mimetype)) {
				$obj = new \C_Document();
		  		$documentData = $obj->retrieve_action($patient_id, $document_id, true, true, true);
				$b64image = base64_encode($documentData);
				if(!empty($b64image)) $b64image = "data:" . $d->mimetype . ";base64," . $b64image;
			}

			if(!in_array($d->mimetype, array("image/gif", "image/jpeg", "image/png"))) {
				throw new \Exception("Not supported type");
			}

			echo json_encode(array('imagebase64' => $b64image, 'mimetype' => $d->mimetype));

		} catch (\Throwable $e) {
			echo json_encode(array('error' => $e->getMessage()));
		}

		exit();
	} else if($ajax_action == "image_operation") {
		try {
			$opts_params = isset($_REQUEST['opts']) && !empty($_REQUEST['opts']) ? $_REQUEST['opts'] : array();
			$operation_action = isset($opts_params['operation']) ? $opts_params['operation'] : "";
			$rotation_degree = isset($opts_params['degree']) ? $opts_params['degree'] : "0";
			$cropbox = isset($opts_params['cropbox']) ? $opts_params['cropbox'] : array();
			

			$mimetype = isset($opts_params['mimetype']) ? $opts_params['mimetype'] : "";
			$imagebase64 = isset($opts_params['imagebase64']) ? $opts_params['imagebase64'] : "";
			$imagebase64 = preg_replace('#^data:image/[^;]+;base64,#', '', $imagebase64);
			$imagebinaryData = base64_decode($imagebase64);

			if(!in_array($mimetype, array("image/gif", "image/jpeg", "image/png"))) {
				throw new \Exception("Not supported type");
			}

			$newimagebase64 = "";
			$imageData = Image::fromData($imagebinaryData);

			if($operation_action == "rotate_right") {
				$imageData->rotate($rotation_degree);
			} else if($operation_action == "rotate_left") {
				$imageData->rotate($rotation_degree);
			} else if($operation_action == "flip_vertical") {
				$imageDataImage = \imageflip($imageData->getImage(), IMG_FLIP_VERTICAL);
			} else if($operation_action == "flip_horizontal") {
				$imageDataImage = \imageflip($imageData->getImage(), IMG_FLIP_HORIZONTAL);
			} else if($operation_action == "crop") {
				$imageData->crop($cropbox['width'], $cropbox['height'], $cropbox['left'], $cropbox['top']);
			}

			if($mimetype == "image/png") {
				$newimagebase64 = $imageData->getDataPNG();
			} else if($mimetype == "image/jpeg") {
				$newimagebase64 = $imageData->getDataJPG();
			} else if($mimetype == "image/gif") {
				$newimagebase64 = $imageData->getDataGIF();
			}

			$imageData->destroy();

			if(empty($newimagebase64)) {
				throw new \Exception("Something went wrong with image");
			}


			$newimagebase64 = "data:" . $mimetype . ";base64," . base64_encode($newimagebase64);
			echo json_encode(array('imagebase64' => $newimagebase64, "mimetype" => $mimetype));

		} catch (\Throwable $e) {
			echo json_encode(array('error' => $e->getMessage()));
		}

		exit();
	}

	exit();
}

?>
<!DOCTYPE html>
<html>
<head>

<title><?php echo xlt('Editor');?></title>
<?php Header::setupHeader(['common', 'opener']); ?>

<!-- OEMR - Cropper -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
<!-- END -->

<style type="text/css">
	.app {
	    bottom: 0;
	    left: 0;
	    position: absolute;
	    top: 0;
	    right: 0;
	    width: 100%;
	}
	@media (min-width: 768px) {
		.header {
		    padding-left: 1.5rem;
		    padding-right: 1.5rem;
		}
	}
	.header {
	    background-color: #666;
	    height: 3rem;
	    overflow: hidden;
	    padding-left: 1rem;
	    padding-right: 1rem;
	    position: relative;
	    z-index: 1;
	}
	.main {
	    background-color: #333;
	    bottom: 0;
	    left: 0;
	    position: absolute;
	    right: 0;
	    top: 3rem;
	    width: 100%;
	    height: calc(100vh - 48px);
	}
	.canvas, .editor {
	    height: 100%;
	}
	.toolbar {
	    background-color: rgba(0,0,0,.5);
	    bottom: 1rem;
	    color: #fff;
	    height: 2rem;
	    left: 50%;
	    margin-left: -8rem;
	    position: absolute;
	    width: 18rem;
	    z-index: 2015;
	}
	.toolbar.disabled button {
		cursor: not-allowed;
        pointer-events: none;
	}
	.toolbar__button {
	    background-color: transparent;
	    border-width: 0;
	    color: #fff;
	    cursor: pointer;
	    display: block;
	    float: left;
	    font-size: .875rem;
	    height: 2rem;
	    text-align: center;
	    width: 2rem;
	}
	.nav__button {
	    background-color: transparent;
	    border-width: 0;
	    color: #fff;
	    cursor: pointer;
	    display: block;
	    float: left;
	    height: 3rem;
	    line-height: 3rem;
	    text-align: center;
	    width: 3rem;
	}
	.nav__item {
	    background-color: transparent;
	    border-width: 0;
	    color: #fff;
	    cursor: pointer;
	    display: block;
	    float: left;
	    height: 3rem;
	    line-height: 3rem;
	    text-align: center;
	}
	.navbar1 {
	    float: right;
	}
	.hideitem {
		display: none !important;
	}
	.loader-container {
		position: fixed;
	    width: 100%;
	    height: 100%;
	    z-index: 10001;
	    background-color: rgba(0,0,0,0.7);
	    display: grid;
	    justify-content: center;
	    align-items: center;
	}
</style>

<script>

var imagebase64 = "";
var imageEle = null;
var cropper = null;
var data = {
	cropping : false,
	type : ''
};
var angleInDegrees=0;

document.addEventListener('DOMContentLoaded', async function () {
	let imageData = await fetchImage("get_image");

	if(imageData === false) {
		document.querySelector('.error-container').classList.remove("hideitem");	
	} else {
		document.querySelector('.app').classList.remove("hideitem");	
	}

	start();
});

async function actionmethods(target) {
	const action = target.getAttribute('data-action') || target.parentElement.getAttribute('data-action');

	switch (action) {
        case 'move':
        case 'crop':
          cropper.setDragMode(action);
          break;

        case 'zoom-in':
          cropper.zoom(0.1);
          break;

        case 'zoom-out':
          cropper.zoom(-0.1);
          break;

        case 'rotate-left':
          await rotateImage(imagebase64, -90);
          break;

        case 'rotate-right':
          await rotateImage(imagebase64, 90);
          break;

        case 'flip-horizontal':
          await flipImage(imagebase64, 'horizontal');
          break;

        case 'flip-vertical':
		  await flipImage(imagebase64, 'vertical');
          break;
        case 'clear':
          clear();
          break;
        case 'cropdone':
          crop();
          break;
        case 'clear_resizing':
          clearResizingImage();
          break;
        case 'resizeimage':
          resizeimage();
          break;
        case 'start_resizing':
          startResizingImage();
          break;

        default:
    }
}

async function fetchImage( ajax_action = "get_image", setImage = true, opts = {}) {
	
	document.querySelector('.loader-container').classList.remove("hideitem");

	const result = await $.ajax({
        type: "POST",
        url: "<?php echo $GLOBALS['webroot'] .'/interface/patient_file/document_image_editor.php'; ?>",
        datatype: "json",
        data: { 
        	ajax_action : ajax_action, 
        	opts : { imagebase64: imagebase64, mimetype : data.type, ...opts}, 
        	document_id : '<?php echo $document_id; ?>', 
        	patient_id : '<?php echo $patient_id; ?>' 
        }
    });

    document.querySelector('.loader-container').classList.add("hideitem");

    if(result != "") {
		let resultJson = JSON.parse(result);

		if(resultJson.hasOwnProperty('error')) {
			alert(resultJson['error']);
			return false;
		}

		if(resultJson.hasOwnProperty('imagebase64')) {
			imagebase64 = resultJson['imagebase64'];
			data.type = resultJson['mimetype'];
		}
	}

	if(setImage === true) {
		setImageData(imagebase64);
	}

	return imagebase64;
}

function setImageData(imgbase64) {
	let ie = document.getElementById('cropjs');

	ie.src = imgbase64;

	//create an image object from the path
	const originalImage = new Image();
	originalImage.src = imgbase64;

	originalImage.addEventListener('load', function() {
		
		const fileSizeKB = Math.round((imgbase64.length * 0.75) / 1024); // Convert bytes to KB
		const fileSizeMB = (fileSizeKB / 1024).toFixed(2); // Convert KB to MB 

		let imgsize = "";
		if(fileSizeKB < 1024) {
			imgsize = fileSizeKB + " KB";
		} else {
			imgsize = fileSizeMB + " MB";
		}

		// Resolution image
		document.querySelector('.resolution_image').innerHTML = "<i>( " + ie.width + " X " + ie.height + " ) " + imgsize + " </i>";
		
	});

	if(cropper && cropper != null) {
		cropper.replace(imgbase64, false);
		imagebase64 = imgbase64;
	}
}

function startResizingImage() {

	//create an image object from the path
	const originalImage = new Image();
	originalImage.src = imagebase64;

	originalImage.addEventListener('load', function() {

		let imgper = 100;
		let imageResizerElement = document.querySelector(".image_sizes");
		let prevValue = imageResizerElement.value;

		// Clear existing options
        imageResizerElement.innerHTML = '';

		while(imgper > 0) {
			let optionText = "";
			let optionValue = "";
			let newWidth = 0;
			let newHeight = 0;


			if(imgper < 100) { 
				newWidth = (originalImage.width * Number("0." + imgper));
				newHeight = (originalImage.height * Number("0." + imgper));

				optionText = "" + imgper + "% (" + Math.round(newWidth) + " x " + Math.round(newHeight) + ")";
				optionValue = imgper;
			} else {
				optionText = "Original";
				optionValue = "original";
			}

			const newOption = document.createElement('option');
            newOption.value = optionValue;
            newOption.text = optionText;
            newOption.selected = optionValue == prevValue ? true : false;

            newOption.setAttribute('data-width', newWidth);
            newOption.setAttribute('data-height', newHeight);

            imageResizerElement.appendChild(newOption);

            imgper = imgper - 10;
		}
		
	});

	update({
      cropped: true,
      cropping: false,
      original_image: imagebase64,
      resizing: true
    });
}

function clearResizingImage() {
  if (data.resizing) {

  	// Set original image
  	if(data.hasOwnProperty('original_image')) {
  		setImageData(data.original_image);
  	}

    update({
      resizing: false
    });
  }
}

function onsizechange(ele) {
	if(ele.value == "original") {
		// Set original image
	  	if(data.hasOwnProperty('original_image')) {
	  		setImageData(data.original_image);
	  	}
	} else {
		let imageResizerElement = document.querySelector(".image_sizes");
		// Get the selected option
        const selectedOption = imageResizerElement.options[imageResizerElement.selectedIndex];

        const newWidth = selectedOption.getAttribute('data-width');
        const newHeight = selectedOption.getAttribute('data-height');

        if(newWidth <= 0 || newHeight <=0) {
        	return false;
        }

		//create an image object from the path
	    const originalImage = new Image();
	    originalImage.src = data.hasOwnProperty('original_image') ? data.original_image : "";
	 
	    //initialize the canvas object
	    //const canvas = document.getElementById('canvas');
	    let canvas = document.createElement("canvas"); 
	    const ctx = canvas.getContext('2d');

	    document.querySelector('.loader-container').classList.remove("hideitem");
	 	
	 	return new Promise((resolve) => {
		    //wait for the image to finish loading
		    originalImage.addEventListener('load', function() {
		 
		        //set the canvas size to the new width and height
		        canvas.width = newWidth;
		        canvas.height = newHeight;
		         
		        //draw the image
		        ctx.drawImage(originalImage, 0, 0, newWidth, newHeight);

		        document.querySelector('.loader-container').classList.add("hideitem");

		        resolve(getCanvasImage(canvas)); 
		    });

	    });
	}
}

function resizeimage(imagePath) {
	if(data.hasOwnProperty('resizing') && data.resizing === true) {
		update({
	      resizing: false
	    });
	}
}

function flipImage(imagePath, direction = '') {
	//create an image object from the path
    const originalImage = new Image();
    originalImage.src = imagePath;
 
    //initialize the canvas object
    //const canvas = document.getElementById('canvas'); 
    let canvas = document.createElement("canvas");
    const ctx = canvas.getContext('2d');

    document.querySelector('.loader-container').classList.remove("hideitem");
 	
 	return new Promise((resolve) => {
	    //wait for the image to finish loading
	    originalImage.addEventListener('load', function() {

		    canvas.width = originalImage.width;
		    canvas.height = originalImage.height;

	        if (direction === 'horizontal') {
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
            } else if (direction === 'vertical') {
                ctx.translate(0, canvas.height);
                ctx.scale(1, -1);
            }

	        // ctx.rotate(degrees*Math.PI/180);
	        ctx.drawImage(originalImage, 0, 0);

	        document.querySelector('.loader-container').classList.add("hideitem");

	        resolve(getCanvasImage(canvas)); 
	    });

    });
}

function rotateImage(imagePath, degrees = 0) {
	//create an image object from the path
    const originalImage = new Image();
    originalImage.src = imagePath;
 
    //initialize the canvas object
    //const canvas = document.getElementById('canvas'); 
    let canvas = document.createElement("canvas");
    const ctx = canvas.getContext('2d');

    document.querySelector('.loader-container').classList.remove("hideitem");
 	
 	return new Promise((resolve) => {
	    //wait for the image to finish loading
	    originalImage.addEventListener('load', function() {

		    canvas.width = originalImage.height;
		    canvas.height = originalImage.width;

	        if(degrees > 0){
	            ctx.translate(originalImage.height, 0);
	        } else {
	            ctx.translate(0, originalImage.width);
	        }

	        ctx.rotate(degrees*Math.PI/180);
	        ctx.drawImage(originalImage, 0, 0);

	        document.querySelector('.loader-container').classList.add("hideitem");

	        resolve(getCanvasImage(canvas)); 
	    });

    });
}

function cropImage(imagePath, newX, newY, newWidth, newHeight) {
    //create an image object from the path
    const originalImage = new Image();
    originalImage.src = imagePath;
 
    //initialize the canvas object
    //const canvas = document.getElementById('canvas');
    let canvas = document.createElement("canvas"); 
    const ctx = canvas.getContext('2d');

    document.querySelector('.loader-container').classList.remove("hideitem");
 	
 	return new Promise((resolve) => {
	    //wait for the image to finish loading
	    originalImage.addEventListener('load', function() {
	 
	        //set the canvas size to the new width and height
	        canvas.width = newWidth;
	        canvas.height = newHeight;
	         
	        //draw the image
	        ctx.drawImage(originalImage, newX, newY, newWidth, newHeight, 0, 0, newWidth, newHeight);

	        document.querySelector('.loader-container').classList.add("hideitem");

	        resolve(getCanvasImage(canvas)); 
	    });

    });
}

function getCanvasImage(canvas) {
	let imgbase64 = canvas.toDataURL(data.type);

	// Set image data
	setImageData(imgbase64);

	return imgbase64;
}

 
function start() {
	imageEle = document.getElementById('cropjs');
	imagebase64 = imageEle.src;
	cropper = new Cropper(imageEle, {
	  dragMode: 'move',
	  autoCrop: false,
	  background: false,
	  center: true,
	  viewMode: 1,
	  ready: () => {
	  	//cropper.zoomTo(0.8);
      },
	  crop({ detail }) {
	    if (detail.width > 0 && detail.height > 0 && !data.cropping) {
	    	update({
              cropping: true,
            });
	    }
	  },
	});
}

function stop() {
  if (cropper) {
    cropper.destroy();
    cropper = null;
  }
}

async function crop() {
  if (data.cropping) {
    croppedData = cropper.getData();
    canvasData =  cropper.getCanvasData();
    cropBoxData = cropper.getCropBoxData();

    let newleft = (cropBoxData['left'] - canvasData['left']);
    newleft = ((newleft / canvasData['width']) * 100);
    newleft = ((canvasData['naturalWidth'] / 100) * newleft);

    let newtop = (cropBoxData['top'] - canvasData['top']);
    newtop = ((newtop / canvasData['height']) * 100);
    newtop = ((canvasData['naturalHeight'] / 100) * newtop);

    let naturalWidth = ((cropBoxData['width'] / canvasData['width']) * 100);
    naturalWidth = ((canvasData['naturalWidth'] / 100) * naturalWidth);

    let naturalHeight = ((cropBoxData['height'] / canvasData['height']) * 100);
    naturalHeight = ((canvasData['naturalHeight'] / 100) * naturalHeight);

  	let cropUrl = await cropImage(imagebase64, newleft, newtop, naturalWidth, naturalHeight);

    update({
      cropped: true,
      cropping: false,
      previousUrl: data.url,
      url: cropUrl,
    });
  }
}

function clear() {
  if (data.cropping) {
    cropper.clear();
    update({
      cropping: false
    });
  }
}

function update(newdata) {
  Object.assign(data, newdata);

  if(data.cropping === true) {
  	document.querySelector('.nav__button[data-action="clear"]').classList.remove("hideitem");
  	document.querySelector('.nav__button[data-action="cropdone"]').classList.remove("hideitem");
  	document.querySelector('.nav__button[data-action="saveimage"]').classList.add("hideitem");
  	document.querySelector('.toolbar').classList.add("disabled");
  } else if(data.resizing === true) { 
  	document.querySelector('.image_sizes').classList.remove("hideitem");
  	document.querySelector('.nav__button[data-action="clear_resizing"]').classList.remove("hideitem");
  	document.querySelector('.nav__button[data-action="resizeimage"]').classList.remove("hideitem");
  	document.querySelector('.nav__button[data-action="saveimage"]').classList.add("hideitem");
  	document.querySelector('.toolbar').classList.add("disabled");
  } else {
  	document.querySelector('.image_sizes').classList.add("hideitem");
  	document.querySelector('.nav__button[data-action="clear_resizing"]').classList.add("hideitem");
  	document.querySelector('.nav__button[data-action="resizeimage"]').classList.add("hideitem");
  	document.querySelector('.nav__button[data-action="clear"]').classList.add("hideitem");
  	document.querySelector('.nav__button[data-action="cropdone"]').classList.add("hideitem");
  	document.querySelector('.nav__button[data-action="saveimage"]').classList.remove("hideitem");
  	document.querySelector('.toolbar').classList.remove("disabled");
  }
  
}

async function saveimage(ele) {
	if(cropper && cropper != null) {
		let imageUrl = imagebase64;

		if(imageUrl != "") {
			if(confirm("Do you want to save/replace document image?")) {
				
				document.querySelector('.loader-container').classList.remove("hideitem");

				const result = await $.ajax({
		            type: "POST",
		            url: "<?php echo $GLOBALS['webroot'] .'/interface/patient_file/document_image_editor.php'; ?>",
		            datatype: "json",
		            data: { 
		            	ajax_action : "save_image", 
		            	imagebase64 : imageUrl, 
		            	document_id : '<?php echo $document_id; ?>', 
		            	patient_id : '<?php echo $patient_id; ?>' 
		            }
		        });

		        document.querySelector('.loader-container').classList.add("hideitem");

				if(result != "") {
					let resultJson = JSON.parse(result);

					if(resultJson.hasOwnProperty('message')) {
						alert(resultJson['message']);

						if (opener && !opener.closed && opener.dlgclose) {
							dlgclose();
							opener.location.reload();
						}
					} else if(resultJson.hasOwnProperty('error')) {
						alert(resultJson['error']);
					} else {
						alert('Something wrong');
					}
				}
			}
		}
	}

	return false;
}

</script>

</head>
<body>
	<div class="app hideitem">
		<header class="header">
			<div>
				<nav class="nav1">
					<div class="nav__item">
						<span class="resolution_image"></span>
					</div>
					<div class="nav__item">
						<select class="image_sizes ml-3 form-control-sm hideitem" onchange="onsizechange(this)">
							<option value="original"><?php echo xlt('Original');?></option>
						</select>
					</div>
				</nav>
			</div>
			<div class="navbar1">
				<nav class="nav">
					<button type="button" data-action="clear_resizing" title="Cancel Resizing (Esc)" class="nav__button nav__button--danger hideitem" onclick="actionmethods(this)"><span class="fa fa-ban"></span></button>
					<button type="button" data-action="resizeimage" title="Resize Image (Enter)" class="nav__button nav__button--success hideitem" onclick="actionmethods(this)"><span class="fa fa-check"></span></button>

					<button type="button" data-action="clear" title="Cancel (Esc)" class="nav__button nav__button--danger hideitem" onclick="actionmethods(this)"><span class="fa fa-ban"></span></button>
					<button type="button" data-action="cropdone" title="OK (Enter)" class="nav__button nav__button--success hideitem" onclick="actionmethods(this)"><span class="fa fa-check"></span></button>
					<button type="button" data-action="saveimage" class="nav__button nav__button--success" onclick="saveimage(this)"><?php echo xlt('SAVE');?></button>
				</nav>
			</div>
		</header>
		<div class="editor-container main">
			<img id="cropjs" src="">
		</div>
		<div class="toolbar">
			<button data-action="move" title="Move (M)" class="toolbar__button" onclick="actionmethods(this)"><span class="fa fa-arrows"></span></button>
			<button data-action="crop" title="Crop (C)" class="toolbar__button" onclick="actionmethods(this)"><span class="fa fa-crop"></span></button>

			<button data-action="start_resizing" title="Resize Image (R)" class="toolbar__button" onclick="actionmethods(this)"><span class="fa fa-compress"></span></button>

			<button data-action="zoom-in" title="Zoom In (I)" class="toolbar__button" onclick="actionmethods(this)"><span class="fa fa-search-plus"></span></button>
			<button data-action="zoom-out" title="Zoom Out (O)" class="toolbar__button" onclick="actionmethods(this)"><span class="fa fa-search-minus"></span></button>
			<button data-action="rotate-left" title="Rotate Left (L)" class="toolbar__button"><span class="fa fa-rotate-left" onclick="actionmethods(this)"></span></button>
			<button data-action="rotate-right" title="Rotate Right (R)" class="toolbar__button" onclick="actionmethods(this)"><span class="fa fa-rotate-right"></span></button>
			<button data-action="flip-horizontal" title="Flip Horizontal (H)" class="toolbar__button" onclick="actionmethods(this)"><span class="fa fa-arrows-h"></span></button>
			<button data-action="flip-vertical" title="Flip Vertical (V)" class="toolbar__button" onclick="actionmethods(this)"><span class="fa fa-arrows-v"></span></button>
		</div>
	</div>
	<div class="error-container hideitem">
		<span><?php echo xlt("Error not supported") ?></span>
	</div>

	<div class="loader-container hideitem">
		<div class="spinner-border text-primary" role="status">
  			<span class="sr-only"><?php echo xlt("Loading...") ?></span>
		</div>
	</div>
	<canvas id="canvas" style="margin-top:650px"></canvas>
</body>
</html>
