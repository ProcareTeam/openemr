let currentchunkvalue = 0;
let chunksArray = [];
let audioChunks = [];
chunksArray[currentchunkvalue] = [];
let resulttext = [];
resulttext[currentchunkvalue] = "";
let selectedbox = {};
let localstream = "";
let intervalId;
let recognition;
let debouncetoggle = false;
let deepgram = false;
var bearertoken = "";
let privatecheckbox = true;
let includepublic = false;
let selectmacroId = "";
let portalurl = "https://hcsteams.com/sign-in";
let portalapiurl = "https://voice-notes.in/vn";
let voiceconvertionurl = "https://nfoldai.com/api";
let invalidlogin = false;
let stopbtnclicked = false;
let recording = false;
let alreadyclickonce = false;
let bagofwordtext = "";
let allbagofwordtext = "";
let selectbowId = "";
let socket;
let sockerurl = "wss://nfoldai.com/ws/";

let textarea;
let startPos;
let endPos;
let textBefore;
let textAfter;
let clientverison = true;

let macrolist = [];
let audiolists = [];

var scriptElement = document.getElementById("vnp_scripttag");
const accountNumber = scriptElement.getAttribute("accountNumber")
  ? scriptElement.getAttribute("accountNumber")
  : "";
const userName = scriptElement.getAttribute("userName")
  ? scriptElement.getAttribute("userName")
  : oemr_authUser;
const pmsUser = scriptElement.getAttribute("userName")
  ? scriptElement.getAttribute("userName")
  : oemr_authUser;

let hostname = "TestDomain";

if (clientverison) {
  hostname = window.location.origin;
}

$(document).ready(function () {
  login();
  fetechoptiondropdown();
  fetechbowoptiondropdown();
});

function formatthevalues() {
  currentchunkvalue = 0;
  chunksArray = [];
  chunksArray[currentchunkvalue] = [];
  resulttext = [];
  resulttext[currentchunkvalue] = "";
}

function capitalizeFirstLetter(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function formattext(text) {
  const lines = text.split("\n");
  const formatted_lines = [];
  for (let i = 0; i < lines.length; i++) {
    let formatted_line = lines[i].trim();
    formatted_line = formatted_line.replace(
      /(^|\n+)([a-z])/g,
      function (match, p1, p2) {
        return p1 + p2.toUpperCase();
      }
    );
    if (formatted_line && !formatted_line.endsWith(".")) {
      if (i != lines.length - 1) formatted_line += ".";
    }
    formatted_lines.push(formatted_line);
  }

  return formatted_lines.join("\n");
}

function customrightclick() {
  alreadyclickonce = true;
  let textarea = document.getElementById("note-textarea");
  textarea.addEventListener("contextmenu", (e) => {
    e.preventDefault();

    bagofwordtext = "";
    let selection = window.getSelection();
    let selectedText = selection.toString();

    if (selectedText.length > 0) {
      bagofwordtext = selectedText;
      document.getElementById("add-to-bag-modal").style.display = "block";
    }
  });
}

function invalidcredentials() {
  if (
    confirm(`-- Invalid Credentials or Subscription Has Got Expired --

If already Signed up, Please enter the valid account number in the Global Settings-Voice Note section.
  
If not Signed up, Please click on the OK button to complete the account registration.`) ==
    true
  ) {
    window.open("https://nfoldai.com/voice-notes.html", "_blank");
  } else {
    stoprecoringbtnfunction();
  }
}

function appenddatalogic(result, platform) {
  if (result.data && result.data != "") {
    resulttext[result.requestID >= 0 ? result.requestID : currentchunkvalue] =
      result.data;
    let texttobeappended = "";

    resulttext.map(function (element, index) {
      texttobeappended += index == 0 ? element : " " + element;
    });
    texttobeappended = texttobeappended.replace(" . ", ". ");

    newVal = `${textBefore ? textBefore : ""}${texttobeappended} ${
      textAfter ? textAfter : ""
    }`;

    let closecommand = false;
    let savemacrocommand = false;
    let updatemacrocommand = false;
    let slectmacrocomand = false;
    //command-code
    let commandarray = [
      {
        valuetobeupdated: function () {
          closecommand = true;
        },
        command: "save and close",
      },
      {
        valuetobeupdated: function () {
          closecommand = true;
        },
        command: "close",
      },
      {
        valuetobeupdated: function () {
          savemacrocommand = true;
        },
        command: "save macro",
      },
      {
        valuetobeupdated: function () {
          updatemacrocommand = true;
        },
        command: "update macro",
      },
      {
        valuetobeupdated: function () {
          slectmacrocomand = true;
        },
        command: "select macro",
      },
    ];

    commandarray.map((command) => {
      if (new RegExp("\\b" + command.command + "\\b", "i").test(newVal)) {
        newVal = newVal.replace(new RegExp(command.command, "gi"), "");
        command.valuetobeupdated();
      }
    });

    newVal = newVal.replace(new RegExp("new line", "gi"), "\n");
    newVal = newVal.replace(new RegExp("new paragraph", "gi"), "\n\n");
    newVal = newVal.replace(new RegExp("new para", "gi"), "\n\n");
    newVal = newVal.replace(new RegExp(" period", "gi"), ".");
    newVal = capitalizeFirstLetterOfSentences(newVal);

    if (newVal.length > 0 && newVal != " ") {
      noteContent = formattext(newVal);
      $("#note-textarea").val(noteContent);
    }

    if (closecommand) {
      $("#save-note-btn").click();
    }
    if (savemacrocommand) {
      $("#savemacro").click();
    }
    if (updatemacrocommand) {
      $("#updatemacro").click();
    }
    if (slectmacrocomand) {
      let mySelect = $("#select-macro");
      mySelect.trigger("click");
    }
  } else if (!result.data) {
    let currentVal = $("#note-textarea").val();
    noteContent = currentVal;
    $("#note-textarea").val(currentVal);
  } else if (result.message) {
    alert(result.message);
    stoprecording();
  }
}

function capitalizeFirstLetterOfSentences(str) {
  var sentences = str.split(". ");
  for (var i = 0; i < sentences.length; i++) {
    sentences[i] = capitalizeFirstLetter(sentences[i]);
  }
  return sentences.join(". ");
}

function getcursortextarea(extravalue) {
  textarea = document.getElementById("note-textarea");
  if (extravalue) {
    let selectedText = textarea.value.substring(
      textarea.selectionStart,
      textarea.selectionEnd
    );
    if (selectedText.length > 0) {
      textarea.value =
        textarea.value.substring(0, textarea.selectionStart) +
        extravalue +
        textarea.value.substring(textarea.selectionEnd);
    } else {
      textarea.value = textarea.value + extravalue;
    }
  }
  startPos = textarea.selectionStart;
  endPos = textarea.selectionEnd;
  textBefore = textarea.value.substring(0, startPos);
  textAfter = textarea.value.substring(endPos, textarea.value.length);
}

function startRecordingmodule() {
  formatthevalues();
  if (recording) {
    recording = false;
    stoprecoringbtnfunction();
  } else {
    getcursortextarea();
    if (invalidlogin) {
      invalidcredentials();
    } else {
      recording = true;
      if (noteContent.length) {
        noteContent += " ";
      }

      socket = new WebSocket(sockerurl);

      const texts = {};
      socket.onmessage = (event) => {
        appenddatalogic(JSON.parse(JSON.parse(event.data)), "socket result");
      };

      socket.onerror = (event) => {
        console.error(event);
        socket.close();
      };

      socket.onclose = (event) => {
        socket = null;
      };

      socket.onopen = () => {
        if ("SpeechRecognition" in window) {
          recognition = new window.SpeechRecognition();
        } else if ("webkitSpeechRecognition" in window) {
          recognition = new window.webkitSpeechRecognition();
        } else if ("MozSpeechRecognition" in window) {
          recognition = new window.MozSpeechRecognition();
        }

        if (typeof recognition !== "undefined") {
          navigator.mediaDevices
            .getUserMedia({ audio: { sampleRate: 96000 } })
            .then((stream) => {
              localstream = stream;
              recorder = new MediaRecorder(stream);

              recorder.addEventListener("dataavailable", function (event) {
                audioChunks.push(event.data);
              });

              recorder.addEventListener("stop", function () {
                const audioBlob = new Blob(audioChunks, { type: "audio/webm" });
                const reader = new FileReader();
                reader.onload = () => {
                  const base64data = reader.result;
                  if (socket) {
                    socket.send(
                      JSON.stringify({
                        type: "convert",
                        token: bearertoken,
                        requestID: currentchunkvalue,
                        data: base64data.split("base64,")[1],
                      })
                    );
                  }
                  currentchunkvalue = currentchunkvalue + 1;
                  audioChunks = [];
                };
                reader.readAsDataURL(audioBlob);
              });

              recognition.lang = "en-US";
              recognition.interimResults = true;

              recognition.onstart = () => {};

              recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                if (event?.results[0]?.isFinal) {
                  recorder.stop();
                } else {
                  appenddatalogic({ data: transcript }, "web engine result");
                }
              };

              recognition.onend = () => {
                if (recognition) {
                  recognition.start();
                }
                if (recorder)
                  if (
                    recorder.state == "inactive" ||
                    recorder.state == "paused" ||
                    recorder.state != "recording"
                  )
                    recorder.start();
              };

              recognition.start();
              recorder.start();
            })
            .catch((err) => console.error(err));
        } else {
          alert("Speech recognition not supported in this browser");
          stoprecoringbtnfunction();
        }
      };

      $("#recording-instructions").html(
        "Please wait.....<strong>Recording in Progress......</strong>"
      );
      $(".circle_ripple").addClass("active");
      $(".circle_ripple-2").addClass("active");

      if (!alreadyclickonce) {
        customrightclick();
      }
    }
  }
}

function debounce(func, delay) {
  let debounceTimer;
  return function () {
    const context = this;
    const args = arguments;
    if (debouncetoggle) {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => func.apply(context, args), delay);
    } else {
      func.apply(context, args);
    }
  };
}

function stoprecoringbtnfunction() {
  $("#start-record-btn").prop("disabled", false);

  $("#recording-instructions").html(
    "Press the <strong>Start Recognition</strong> button and allow access."
  );

  $(".circle_ripple").removeClass("active");
  $(".circle_ripple-2").removeClass("active");
  debounce(stoprecording(), 2000);
  // clearInterval(intervalId);
}

function stoprecording() {
  if (recognition) {
    recognition.stop();
    recognition = null;
  }

  if (recorder) {
    recorder.stop();
    recorder = null;
  }

  if (socket) {
    socket.close();
    socket = "";
    if (localstream)
      localstream.getTracks().forEach(function (track) {
        track.stop();
      });
  }
}

function fetechoptiondropdown() {
  var myHeaders = new Headers();
  myHeaders.append("Content-Type", "application/json");

  var raw = JSON.stringify({
    accountNumber: accountNumber,
    pmsUser: userName,
    voicedraftsID: "",
  });

  var requestOptions = {
    method: "POST",
    headers: myHeaders,
    body: raw,
    redirect: "follow",
  };

  fetch(`${portalapiurl}/Account/GetVoiceNotesList`, requestOptions)
    .then((response) => response.json())
    .then((result) => {
      if (result.length > 0) {
        macrolist = result;

        $("#select-macro").empty();
        $("#select-macro").append(new Option("Select a Macro", ""));
        result.map((eachrow, key) => {
          if (eachrow.availability == "1") {
            $("#select-macro").append(
              new Option(
                `${eachrow.macroName} (${
                  eachrow.availability == "1" ? "Private" : "Public"
                })`,
                eachrow.voicedraftsID
              )
            );
          }
        });
      }
    })
    .catch((error) => console.log("GetVoiceNotesList-error", error));
}

function fetechbowoptiondropdown() {
  var myHeaders = new Headers();
  myHeaders.append("Content-Type", "application/json");

  var raw = JSON.stringify({
    accountNumber: accountNumber,
    pmsuser: pmsUser,
    categoryId: "",
    bowId: "",
  });

  var requestOptions = {
    method: "POST",
    headers: myHeaders,
    body: raw,
    redirect: "follow",
  };

  fetch(`${portalapiurl}/Account/GetbowList`, requestOptions)
    .then((response) => response.json())
    .then((result) => {
      if (result.length > 0) {
        allbagofwordtext = result[0].bow;
        selectbowId = result[0].bowid;
        $("#allbagofwords").val(allbagofwordtext);
      }
    })
    .catch((error) => console.log("GetVoiceNotesList-error", error));
}

function customconfirmmsg(msg) {
  let val = confirm(msg);
  if (val == true) {
    window.open(portalurl, "_blank");
  }
}

function getCookie(name) {
  const cookies = document.cookie.split("; ");
  for (let i = 0; i < cookies.length; i++) {
    const cookie = cookies[i].split("=");
    if (cookie[0] === name) {
      return cookie[1];
    }
  }
  return "";
}

function login() {
  var myHeaders = new Headers();
  myHeaders.append("Content-Type", "application/json");

  var raw = JSON.stringify({
    type: "login",
    data: {
      accountNumber: accountNumber,
      userName: userName,
      hostname: hostname,
    },
  });

  let loginscoket = new WebSocket(sockerurl);

  loginscoket.onmessage = (event) => {
    responsedata = JSON.parse(event.data);
    if (responsedata?.token) {
      let clientdata = responsedata?.user[0][0];
      if (
        clientdata.remainingMinutes <= 50 &&
        clientdata.remainingMinutes > 26
      ) {
        customconfirmmsg(
          "Remaining Subscription Minutes is less than 50. Do you want to renew the subscription?"
        );
      } else if (
        clientdata.remainingMinutes <= 25 &&
        clientdata.remainingMinutes > 10
      ) {
        customconfirmmsg(
          "Remaining Subscription Minutes is less than 25. Do you want to renew the subscription?"
        );
      } else if (clientdata.remainingMinutes <= 10) {
        customconfirmmsg(
          "Remaining Subscription Minutes is less than 10. Do you want to renew the subscription?"
        );
      }
      bearertoken = responsedata.token.token;

      $(".recordSection").html(
        `<div class="container-fluid">
              <div class="row">
                <div class="col-lg-12 text-right mt-3">
                  <button id="recordWBtnClose" class="btn btn-primary btn-sm btn-round btn-icon pt-1">
                    <i class="fa fa-times" aria-hidden="true"></i>
                  </button>
                </div>
              </div>
              <div class="row">
                <div class="col-lg-3"></div>
                <div class="col-lg-6">
                  <div class="rec-section">
                    <span class="text-center btn-block-record" id="start-record-btn" >
                      <div class="box">
                        <div class="circle_ripple circle_ripple1"></div>
                         <div class="circle_ripple-2 circle_ripple-22">
                        </div>
                        <div class="circles circles1">
                          <div class="circles-2 circles-22">
                            <i class="fa fa-microphone" aria-hidden="true"></i>
                          </div>
                        </div>
                      </div>
                    </span> <br> <br> <br> <br>
                    <p class="text-center" id="recording-instructions">Press the <strong>Start Recognition</strong> button and allow access.</p>
                    <textarea class="result-box note p-2 no-resize form-control" name="note-textarea" id="note-textarea" value=""  style="color: #000;" placeholder="Your notes..." rows="8"></textarea>
                    <div class="text-center">
                      <button class="btn btn-primary btn-sm btn-round" id="save-note-btn">
                        Save
                      </button>
                      <button class="btn btn-primary btn-sm btn-round" id="clearDectation">
                        Clear
                      </button>
                    </div>
                    <label class="select-macro-label">Select Macro</label>
                    <select class="form-control select-macro-box" id="select-macro">
                    </select>
                    <label class="macLabel">
                      <input name="macroCheckBox" class="macroCheckBox" type="checkbox" value="firstmacrocheck"  />&nbsp;&nbsp;Include Public
                    </label>
                    <div class="text-center">
                      <button id="savemacro" class="btn btn-primary btn-sm btn-round save-as-macro-btn" value="save">
                        Save as New Macro
                      </button>
                      <button id="updatemacro" class="btn btn-primary btn-sm btn-round save-as-macro-btn" value="update">
                        Update Macro
                      </button>
                    </div>
                    <div class="mt-2">
                      <label class="select-macro-label">Bag of Words</label>
                      <textarea readonly="true" class="result-box note p-2 no-resize form-control" name="allbagofwords" id="allbagofwords" value=""  style="color: #000;" placeholder="Bag of words..." rows="4"></textarea>
                      <div class="text-center">
                        <button id="savebow" class="btn btn-primary btn-sm btn-round save-as-bow-btn" value="save">
                          Save
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-lg-3"></div>
              </div>
            </div>
            <div id="mySizeChartModal" class="ebcf_modal">
              <div class="ebcf_modal-content">
                <span id="ebcf_close">&times;</span>
                <label>Please Enter Macro Name:</label>
                <input type="text" id="macroname" class="form-control" />
                <label>
                  <input class="macroprivatebtn" name="macroModalCheckBox" type="checkbox"  checked  />&nbsp;&nbsp;Private
                </label><br/><br/>
                <button id="save-macro-api" class="btn btn-primary">
                  Save Macro
                </button>
              </div>
            </div>
            <div id="add-to-bag-modal" class="ebcf_modal">
              <div class="ebcf_modal-content">
                <div class="text-right mt-3">
                  <button id="recordaddbowClose" class="btn btn-primary btn-sm btn-round btn-icon pt-1">
                    <i class="fa fa-times" aria-hidden="true"></i>
                  </button>
                </div>
                <br>
                <button id="addBagofWords" class="btn btn-primary w-100" style="width: 100%;">
                  Add to Bag of Words
                </button>
              </div>
            </div>
            <div id="audiotag">
            </div>
            <div id="dialog" title="Basic dialog">
              <p id="dialogtext">This is the default dialog which is useful for displaying information. The dialog window can be moved, resized and closed with the &apos;x&apos; icon.</p>
            </div>`
      );
      ebSpan = document.getElementById("ebcf_close");
      if (clientverison) {
        startRecordingmodule();
        $("#note-textarea").val(selectedbox.value + " ");
        getcursortextarea();
      }
      $(document).ready(function () {
        $("#note-textarea").click(function (event) {
          if (event.button === 0) {
            formatthevalues();
            getcursortextarea();
          }
        });

        $("#note-textarea").on("keydown", function (event) {
          if (event?.originalEvent?.code.includes("Key")) {
            event.preventDefault();
            formatthevalues();
            getcursortextarea(event?.originalEvent?.key);
          }
        });

        $("#note-textarea").on("keypress", function (event) {
          if (event.key === "Enter") {
            event.preventDefault();
            $("#note-textarea").val($("#note-textarea").val() + "\n");
            formatthevalues();
            getcursortextarea();
          }
        });

        $("#note-textarea").on("keyup", function (event) {
          if (event.key === "Backspace") {
            formatthevalues();
            getcursortextarea();
          }
        });
      });
    } else {
      invalidlogin = responsedata.message;
    }
    loginscoket.close();
    loginscoket = "";
  };

  loginscoket.onopen = () => {
    loginscoket.send(raw);
  };
}

let recorder;

var noteTextarea = $("#note-textarea");
var instructions = $("#recording-instructions");
var notesList = $("ul#notes");
var ebBtn = document.getElementById("mySizeChart");
let ebSpan = "";

var noteContent = "";

$(document).ready(function () {
  document.onclick = function (event) {
    var clickedElement = event.target;
    if (
      clickedElement.tagName.toLowerCase() == "textarea" ||
      clickedElement.tagName.toLowerCase() == "input"
    ) {
      if (clickedElement.id != "note-textarea") {
        selectedbox = clickedElement;
      }
    }
  };

  var notes = getAllNotes();
  renderNotes(notes);

  $(document).on("click", ".recordSection #start-record-btn", function (event) {
    startRecordingmodule();
  });

  $(document).on("click", "#addBagofWords", () => {
    allbagofwordtext = allbagofwordtext
      ? allbagofwordtext + ", " + bagofwordtext.replace(/ /g, ", ")
      : bagofwordtext;
    $("#allbagofwords").val(allbagofwordtext);
    bagofwordtext = "";
    document.getElementById("add-to-bag-modal").style.display = "none";
  });

  function findbrowser() {
    let browserInfo = navigator.userAgent;
    let browser;
    if (browserInfo.includes("Opera") || browserInfo.includes("Opr")) {
      browser = "Opera";
    } else if (browserInfo.includes("Edg")) {
      browser = "Edge";
    } else if (browserInfo.includes("Chrome")) {
      browser = "Chrome";
    } else if (browserInfo.includes("Safari")) {
      browser = "Safari";
    } else if (browserInfo.includes("Firefox")) {
      browser = "Firefox";
    } else {
      browser = "unknown";
    }
    return browser;
  }

  $(document).on("click", "#recordaddbowClose", function (e) {
    document.getElementById("add-to-bag-modal").style.display = "none";
  });

  $(document).on("click", ".recordSection #pause-record-btn", function (e) {
    $("#start-record-btn").prop("disabled", false);

    $("#recording-instructions").html(
      "Voice recognition <strong>Paused</strong>."
    );

    $(".circle_ripple").removeClass("active");
    $(".circle_ripple-2").removeClass("active");
    debounce(stoprecording(), 2000);
    // clearInterval(intervalId);
  });

  noteTextarea.on("input", function () {
    noteContent = $(this).val();
    // clearInterval(intervalId);
  });

  $(document).on("click", ".recordSection #save-note-btn", function (e) {
    let textareacontent = $("#note-textarea").val();

    saveNote(new Date().toLocaleString(), textareacontent);

    noteContent = "";
    renderNotes(getAllNotes());
    $("#recording-instructions").html("Note saved successfully.");
    //instructions.html("Note saved successfully.");

    $(".recordSection").fadeOut("slow");

    if (textareacontent.length > 0) {
      var noteVale = document.getElementById("note-textarea").value;
      document.getElementsByClassName("note-active").value += noteVale;
      selectedbox.value = noteVale;
    }

    $(".circle_ripple-22").removeClass("active");
    $(".circle_ripple1").removeClass("active");
    $(".recordSection").fadeOut();

    $("#note-textarea").val("");
    debounce(stoprecording(), 2000);
  });

  $(document).on("click", ".recordSection .save-as-macro-btn", function (e) {
    if (this.value == "save") {
      selectmacroId = "";
    }
    stoprecoringbtnfunction();
    document.getElementById("mySizeChartModal").style.display = "block";
  });

  $(document).on("click", ".recordSection .save-as-bow-btn", function (e) {
    let allbagofwords = $("#allbagofwords").val();
    if (allbagofwords.length > 0) {
      var myHeaders = new Headers();
      myHeaders.append("Content-Type", "application/json");

      var raw = JSON.stringify({
        bowid: selectbowId ? selectbowId.toString() : "0",
        accountNumber: accountNumber,
        pmsuser: pmsUser,
        bow: allbagofwords.toString(),
        categorybow: "",
        categoryId: "",
        categoryName: "",
      });

      var requestOptions = {
        method: "POST",
        headers: myHeaders,
        body: raw,
        redirect: "follow",
      };

      fetch(`${portalapiurl}/Account/SaveBowDetails`, requestOptions)
        .then((response) => response.json())
        .then((result) => {
          if (result.status == "success") {
            fetechbowoptiondropdown();
          } else {
            alert("Something went wrong");
          }
        })
        .catch((error) => alert("SaveVoiceNotes-error", error));
    } else {
      alert("Please Enter Some Values into Bag to Words");
    }
  });

  $(document).on("click", ".recordSection #stopDictation", function () {
    stoprecoringbtnfunction();
  });

  notesList.on("click", function (e) {
    e.preventDefault();
    var target = $(e.target);

    if (target.hasClass("listen-note")) {
      var content = target.closest(".note").find(".content").text();
      readOutLoud(content);
    }

    if (target.hasClass("delete-note")) {
      var dateTime = target.siblings(".date").text();
      deleteNote(dateTime);
      target.closest(".note").remove();
    }
  });

  function readOutLoud(message) {
    var speech = new SpeechSynthesisUtterance();

    speech.text = message;
    speech.volume = 1;
    speech.rate = 1;
    speech.pitch = 1;

    window.speechSynthesis.speak(speech);
  }

  /*-----------------------------
      Helper Functions 
------------------------------*/

  function renderNotes(notes) {
    var html = "";
    if (notes.length) {
      notes.forEach(function (note) {
        html += `<li class="note">
        <p class="header">
          <span class="date">${note.date}</span>
          <a href="#" class="listen-note" title="Listen to Note">Listen to Note</a>
          <a href="#" class="delete-note" title="Delete">Delete</a>
        </p>
        
      </li>`;
      });
    } else {
      html = '<li><p class="content">You don\'t have any notes yet.</p></li>';
    }
    notesList.html(html);
  }

  function saveNote(dateTime, content) {
    localStorage.setItem("note-" + dateTime, content);
  }

  function getAllNotes() {
    var notes = [];
    var key;
    for (var i = 0; i < localStorage.length; i++) {
      key = localStorage.key(i);

      if (key.substring(0, 5) == "note-") {
        notes.push({
          date: key.replace("note-", ""),
          content: localStorage.getItem(localStorage.key(i)),
        });
      }
    }
    return notes;
  }

  function deleteNote(dateTime) {
    localStorage.removeItem("note-" + dateTime);
  }

  $(document).on("click", ".recordSection #clearDectation", function () {
    $("#note-textarea").val("");
    noteContent = "";
    formatthevalues();
    getcursortextarea();
  });

  var selector = ".form-control";
  $(selector).each(function () {
    $(this).on("click", function () {
      $(selector).removeClass("note-active");
      $(this).removeClass("note-active").addClass("note-active");
    });
  });

  window.document.body.insertAdjacentHTML(
    "afterbegin",
    '<div class="recordSection" style="overflow-y: auto;"> </div>'
  );

  $(document).ready(function () {
    $("#save-macro-api").click(function () {
      let macroname = $("#macroname").val();
      if (macroname.length > 0) {
        var myHeaders = new Headers();
        myHeaders.append("Content-Type", "application/json");

        var raw = JSON.stringify({
          accountNumber: accountNumber,
          pmsUser: userName,
          voiceNotes: $("#note-textarea").val().toString(),
          availability: privatecheckbox ? "1" : "2", //1-private 2-public
          voicedraftsID: selectmacroId.toString(),
          updatedOn: "",
          owner: "",
          macroName: macroname,
        });

        var requestOptions = {
          method: "POST",
          headers: myHeaders,
          body: raw,
          redirect: "follow",
        };

        fetch(`${portalapiurl}/Account/SaveVoiceNotes`, requestOptions)
          .then((response) => response.json())
          .then((result) => {
            if (result.status == "success") {
              document.getElementById("mySizeChartModal").style.display =
                "none";
              fetechoptiondropdown();
            } else {
              alert("Something went wrong");
            }
          })
          .catch((error) => alert("SaveVoiceNotes-error", error));
      } else {
        alert("Please Enter the Macro Name");
      }
    });

    $(".macroprivatebtn").change(function () {
      privatecheckbox = privatecheckbox ? false : true;
    });

    $(".macroCheckBox").change(function () {
      includepublic = includepublic ? false : true;
      $("#select-macro").empty();
      $("#select-macro").append(new Option("Select a Macro", ""));
      if (includepublic == false) {
        macrolist.map((eachrow, key) => {
          if (eachrow.availability == "1") {
            $("#select-macro").append(
              new Option(
                `${eachrow.macroName} (${
                  eachrow.availability == "1" ? "Private" : "Public"
                })`,
                eachrow.voicedraftsID
              )
            );
          }
        });
      } else {
        macrolist.map((eachrow, key) => {
          $("#select-macro").append(
            new Option(
              `${eachrow.macroName} (${
                eachrow.availability == "1" ? "Private" : "Public"
              })`,
              eachrow.voicedraftsID
            )
          );
        });
      }
    });

    $("#recBtn").click(function (e) {
      e.preventDefault();
      $("button#recordWBtn").toggle("slow");
      $("#_autoRecOnOffBtn").toggle("slow");
      $(this).toggleClass("fa-toggle-on fa-toggle-off");
      $("#recBtn").css(["transition", "all 0.6s ease-in-out"]);
    });

    //Delay toggle button
    $("#recBtn2").click(function (e) {
      e.preventDefault();
      debouncetoggle = !debouncetoggle;
      $(this).toggleClass("fa-toggle-off fa-toggle-on");
      $("#recBtn2").css(["transition", "all 0.6s ease-in-out"]);
    });

    $("#recBtn3").click(function (e) {
      e.preventDefault();
      deepgram = !deepgram;
      $(this).toggleClass("fa-toggle-off fa-toggle-on");
      $("#recBtn3").css(["transition", "all 0.6s ease-in-out"]);
    });

    var switchOn = true;
    $(document).on("click", "#_autoRecOnOffBtn", function (event) {
      switchOn = switchOn ? false : true;

      if (switchOn == true) {
        this.innerHTML = "Auto rec OFF";
      } else {
        this.innerHTML = "Auto rec ON";
      }
    });
    //Voice recording window open
    $("#recordWBtn").click(function () {
      if (invalidlogin) {
        invalidcredentials();
        return;
      }

      if (recording) {
        recording = false;
        stoprecoringbtnfunction();
      }
      if (!selectedbox) {
        alert("Please Select a Textarea or an Input field");
        return false;
      }
      formatthevalues();
      $(".recordSection").fadeIn();
      $("#note-textarea").val(selectedbox.value + " ");

      if (switchOn == true) {
        if (!invalidlogin) {
          let element = document.getElementById("start-record-btn");
          element ? element.click() : "";
        } else {
          let element = document.getElementById("stopDictation");
          element ? element.click() : "";
        }
      } else {
        let element = document.getElementById("stopDictation");
        element ? element.click() : "";
      }
    });

    $(document).on("click", ".recordSection #recordWBtnClose", function () {
      stopbtnclicked = true;
      debounce(stoprecording(), 2000);
      $(".circle_ripple-22").removeClass("active");
      $(".circle_ripple1").removeClass("active");
      $(".recordSection").fadeOut();
    });

    ebSpan.onclick = function () {
      document.getElementById("mySizeChartModal").style.display = "none";
    };

    window.onclick = function (event) {
      if (event.target == document.getElementById("mySizeChartModal")) {
        document.getElementById("mySizeChartModal").style.display = "none";
      }
    };
  });

  $("#select-macro").on("change", function () {
    selectmacroId = this.value;
    if (selectmacroId) {
      // $("#updatemacro").show();
      macrolist.map((eachrow, key) => {
        if (eachrow.voicedraftsID == this.value.toString()) {
          $("#note-textarea").val(
            $("#note-textarea").val() + " " + eachrow.voiceNotes
          );
          noteContent = eachrow.macroName;
          $("#macroname").val(eachrow.macroName);
        }
      });
    } else {
      // $("#updatemacro").hide();
      $("#note-textarea").val("");
    }
  });

  var styles = {
    "#textContainer": {
      border: "blue solid 2px;",
      "background-color": "#ff0;",
    },
    "#textContainer > button": {
      color: "#f00",
    },

    ".form-control": { "margin-bottom": "5px!important" },
    ".btn-block-record": {
      background:
        "linear-gradient(to right, #C04848 0%, #480048  51%, #C04848  100%);",
      "border-color": "transparent;",
    },
    ".btn-block-record:hover": {
      "background-color": "#bd2130;",
      "border-color": "#bd2130;",
      "box-shadow": "0 0.125rem 0.25rem rgb(0 0 0 / 8%);",
    },
    ".btn-block-record:hover i": {
      "background-color": "#bd2130;",
      "border-color": "#bd2130;",
      "box-shadow": "0 0.125rem 0.25rem rgb(0 0 0 / 8%);",
    },
    ".btn-block-record:hover i": {
      "box-shadow": "none;",
      "border-color": "transparent;",
      "background-color": "transparent;",
    },
    ".macLabel": {
      /* Admin */
      /*  color: "#fff",/*
    },
    ".btn-block-record i": {
      "font-size": " 40px;",
      color: "#fff;",
      "box-shadow": "none;",
      "border-radius": "50%;",
      padding: " 5px;",
      cursor: "pointer",
    },
    ".btn-block-record2 i": {
      "font-size": " 40px;",
      color: " #fff;",
      "box-shadow": " none;",
      "border-radius": " 50%;",
      padding: " 5px;",
      cursor: " pointer;",
    },
    ".btn-block-record3 i": {
      "font-size": " 40px;",
      color: " #fff;",
      "box-shadow": " none;",
      "border-radius": " 50%;",
      padding: " 5px;",
      cursor: " pointer;",
    },
    ".btn-block-record i:hover": {
      "font-size": " 40px;",
      color: " #fff;",
      "box-shadow": " none;",
      "border-radius": " 50%;",
      padding: " 5px;",
    },
    ".editBtnSection": {
      position: " absolute;",
      right: " 20px;",
      top: " 7px;",
    },

    ".box": {
      position: "relative;",
    },
    ".circle_ripple": {
      height: " 100px;",
      width: " 100px;",
      background: " #00cfd1b8;",
      "border-radius": " 50%;",
      position: " absolute;",
      left: " 0;",
      top: " -25px;",
      right: " 0;",
      "z-index": " 0;",
      bottom: " 0;",
      margin: "0 auto;",
    },
    ".circle_ripple.active": {
      height: " 55px;",
      width: " 55px;",
      background: " #00cfd1;",
      "border-radius": " 50%;",
      "-webkit-animation-name": " ripple 2s infinite;",
      animation: " ripple 2s infinite;",
      position: " absolute;",
      left: " 0;",
      top: " 00px;",
      right: " 0;",
      "z-index": " 0;",
      bottom: " 0;",
      margin: "0 auto;",
    },
    ".circle_ripple-pat.active": {
      height: " 55px;",
      width: " 55px;",
      background: " #00cfd1;",
      "border-radius": " 50%;",
      "-webkit-animation-name": " ripple 2s infinite;",
      animation: " ripple 2s infinite;",
      position: " absolute;",
      left: " 0;",
      top: " 00px;",
      right: " 0;",
      "z-index": " 0;",
      bottom: " 0;",
      margin: "0 auto;",
    },

    ".circle_ripple-2.active": {
      height: " 55px;",
      width: " 55px;",
      background: " #00cfd1;",
      "border-radius": " 50%;",
      "-webkit-animation-name": " ripple 2s infinite;",
      animation: " ripple-2 2s infinite;",
      position: " absolute;",
      left: " 0;",
      top: " 00px;",
      right: " 0;",
      bottom: " 0;",
      margin: "0 auto;",
    },
    ".circle_ripple-2-pat.active": {
      height: " 55px;",
      width: " 55px;",
      background: " #00cfd1;",
      "border-radius": " 50%;",
      "-webkit-animation-name": " ripple 2s infinite;",
      animation: " ripple-2 2s infinite;",
      position: " absolute;",
      left: " 0;",
      top: " 00px;",
      right: " 0;",

      bottom: " 0;",
      margin: "0 auto;",
    },

    ".circles": {
      position: " absolute;",
      top: " 0px;",
      bottom: " 0;",
      left: " 0px;",
      right: " 0;",
      margin: "0 auto;",
      "z-index": " 99;",
      width: " 50px;",
    },
    ".circles-pat": {
      position: " absolute;",
      top: " 0px;",
      bottom: " 0;",
      left: " 0px;",
      right: " 0;",
      margin: "0 auto;",
      "z-index": " 99;",
      width: " 50px;",
    },

    ".circles-2 .fa": {
      color: "#fff;",
      "z-index": "99;",
    },
    ".circles-2-pat .fa": {
      color: "#fff;",
      "z-index": "99;",
    },

    "#notes": { padding: "0;", "max-height": "150px;", overflow: "auto;" },
    "#notes .note": {
      "list-style": "none;",
    },
    "#notes table": {
      width: "100%;",
    },

    "button#recordWBtn": {
      position: " fixed;",
      bottom: " 65px;",
      right: " 15px;",
      "z-index": " 999;",
    },

    ".recordSection": {
      position: "fixed;",
      width: "100%;",
      height: "100%;",
      top: "0;",
      bottom: "0;",
      right: "0;",
      display: "none;",
      left: "0;",
      margin: "auto 0;",
      /* Client */
      background: "white",
      /* Admin */
      /*background: "#000000c4;", */
      "z-index": "9999;",
    },
    ".rec-section": {
      padding: " 20px;",
      "border-radius": " 13px;",
      position: " relative;",
      width: " 100%;",
      top: " 20%;",
      bottom: " 0;",
      left: " 0;",
      right: " 0;",
      margin: " auto;",
    },
    ".result-box": {
      "white-space": "pre-wrap",
    },
    ".result-box:focus": {},

    ".select-macro-box": {
      width: "100%;",
      background: "transparent;",
      /* Client */
      color: "black",
      /* Admin */
      /* color: "#fff;", */
    },
    ".select-macro-box option": {
      /* Admin */
      /* background: "#000000a8", */
    },
    ".select-macro-label": {
      /*Admin */
      /*color: "#fff;", */
    },
    /* The Modal (background) */
    ".ebcf_modal": {
      display: "none;",
      position: "fixed;",
      "z-index": "1;",
      "padding-top": "100px;",
      left: "0;",
      top: "0;",
      width: "100%;",
      height: "100%;",
      overflow: "auto;",
      "background-color": "rgb(0,0,0);",
      "background-color": "rgba(0,0,0,0.4);",
    },

    /* Modal Content */
    ".ebcf_modal-content": {
      "background-color": "#fefefe;",
      margin: "auto;",
      padding: "20px;",
      border: "1px solid #888;",
      width: "40%;",
    },

    /* The Close Button */
    "#ebcf_close": {
      color: " #aaaaaa;",
      float: "right;",
      "font-size": "28px;",
      "font-weight": "bold;",
      "line-height": "20px",
    },

    "#ebcf_close:hover": {
      color: "#000;",
      "text-decoration": "none;",
      cursor: "pointer;",
    },
    "#ebcf_close:focus": {
      color: "#000;",
      "text-decoration": "none;",
      cursor: "pointer;",
    },
  };

  var newStyle = document.createElement("style");
  newStyle.type = "text/css";
  newStyle.appendChild(document.createTextNode(getCSS()));

  document.querySelector("body").appendChild(newStyle);

  function getCSS() {
    var css = [];
    for (let selector in styles) {
      let style = selector + " {";

      for (let prop in styles[selector]) {
        style += prop + ":" + styles[selector][prop];
      }

      style += "}";

      css.push(style);
    }

    return css.join("\n");
  }
});
