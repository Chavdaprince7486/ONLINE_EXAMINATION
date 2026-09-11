/*==================================================
        EXAMSPHERE PROFILE JS
        PART 1
==================================================*/

document.addEventListener("DOMContentLoaded",function(){

/*=========================================
        PROFILE PHOTO PREVIEW
=========================================*/

const profilePhoto=
document.getElementById("profilePhoto");

const profilePreview=
document.getElementById("profilePreview");

if(profilePhoto){

profilePhoto.addEventListener(

"change",

function(e){

const file=e.target.files[0];

if(!file){

return;

}

/* File Type */

const allowed=[

"image/jpeg",

"image/png",

"image/jpg"

];

if(!allowed.includes(file.type)){

Swal.fire({

icon:"error",

title:"Invalid Image",

text:"Please select JPG or PNG image."

});

profilePhoto.value="";

return;

}

/* Max Size */

if(file.size>

2*1024*1024){

Swal.fire({

icon:"warning",

title:"Image Too Large",

text:"Maximum size is 2 MB."

});

profilePhoto.value="";

return;

}

/* Live Preview */

const reader=

new FileReader();

reader.onload=function(event){

if(profilePreview){

profilePreview.src=

event.target.result;

}

};

reader.readAsDataURL(file);

});

}

/*=========================================
        EDIT BUTTON
=========================================*/

const editBtn=

document.querySelector(

".edit-profile-btn"

);

if(editBtn){

editBtn.addEventListener(

"click",

function(){

const nameInput=

document.querySelector(

'input[name="full_name"]'

);

const form=

document.querySelector(

"#profileForm"

);

if(nameInput){

nameInput.focus({

preventScroll:false

});

}

if(form){

window.scrollTo({

top:

form.offsetTop-100,

behavior:"smooth"

});

}

});

}

/*=========================================
        TAB ACTIVE
=========================================*/

const tabs=

document.querySelectorAll(

".tab-btn"

);

tabs.forEach(function(tab){

tab.addEventListener(

"click",

function(){

tabs.forEach(function(btn){

btn.classList.remove(

"active"

);

});

this.classList.add(

"active"

);

});

});

});

/*==================================================
        PROFILE SAVE (AJAX)
==================================================*/

const profileForm =
document.getElementById("profileForm");

const examSphereCsrfToken =
profileForm
? (
    profileForm.querySelector(
        'input[name="csrf_token"]'
    )?.value || ""
)
: (
    document.querySelector(
        'meta[name="csrf-token"]'
    )?.getAttribute("content") || ""
);

if(profileForm){

profileForm.addEventListener(

"submit",

function(e){

e.preventDefault();

const formData =
new FormData(profileForm);

const saveBtn =
profileForm.querySelector(
".primary-btn"
);

if(!saveBtn){

return;

}

const oldText =
saveBtn.innerHTML;

saveBtn.disabled=true;

saveBtn.innerHTML=
'<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

fetch(

"ajax/update_profile.php",

{

method:"POST",

body:formData,

credentials:"same-origin",

headers:{

"Accept":
"application/json"

}

}

)

.then(res=>{

if(!res.ok){

throw new Error(
"Profile request failed."
);

}

return res.json();

})

.then(data=>{

saveBtn.disabled=false;

saveBtn.innerHTML=oldText;

Swal.fire({

icon:data.status,

title:data.title,

text:data.message,

confirmButtonColor:"#556B2F"

});

})

.catch(()=>{

saveBtn.disabled=false;

saveBtn.innerHTML=oldText;

Swal.fire({

icon:"error",

title:"Server Error",

text:"Unable to save profile."

});

});

});

}

/*==================================================
        PROFILE PHOTO UPLOAD
==================================================*/

if(profilePhoto){

profilePhoto.addEventListener(

"change",

function(){

if(this.files.length===0){

return;

}

const uploadData=
new FormData();

uploadData.append(

"profile_photo",

this.files[0]

);

uploadData.append(

"csrf_token",

examSphereCsrfToken

);

fetch(

"ajax/upload_profile_photo.php",

{

method:"POST",

body:uploadData,

credentials:"same-origin",

headers:{

"Accept":
"application/json"

}

}

)

.then(res=>{

if(!res.ok){

throw new Error(
"Photo upload failed."
);

}

return res.json();

})

.then(data=>{

if(data.status==="success"){

Swal.fire({

icon:"success",

title:"Uploaded",

text:data.message,

timer:1800,

showConfirmButton:false

});

if(

data.photo &&
profilePreview

){

profilePreview.src=

data.photo+

"?t="+

Date.now();

}

}else{

Swal.fire({

icon:"error",

title:"Upload Failed",

text:data.message

});

}

})

.catch(()=>{

Swal.fire({

icon:"error",

title:"Server Error",

text:"Unable to upload image."

});

});

});

}

/*==================================================
        CHANGE PASSWORD
==================================================*/

const changePasswordBtn =
document.querySelector(".full-btn");

if(changePasswordBtn){

changePasswordBtn.addEventListener(

"click",

function(){

Swal.fire({

title:"Change Password",

html:`

<input
id="currentPassword"
class="swal2-input"
type="password"
autocomplete="current-password"
placeholder="Current Password">

<input
id="newPassword"
class="swal2-input"
type="password"
autocomplete="new-password"
placeholder="New Password">

<input
id="confirmPassword"
class="swal2-input"
type="password"
autocomplete="new-password"
placeholder="Confirm Password">

`,

confirmButtonText:"Update Password",

confirmButtonColor:"#556B2F",

showCancelButton:true,

cancelButtonText:"Cancel",

preConfirm:()=>{

const current=document
.getElementById("currentPassword").value;

const password=document
.getElementById("newPassword").value;

const confirm=document
.getElementById("confirmPassword").value;

if(

current==="" ||

password==="" ||

confirm===""

){

Swal.showValidationMessage(

"All fields are required."

);

return false;

}

if(password.length<8){

Swal.showValidationMessage(

"Password must contain at least 8 characters."

);

return false;

}

if(password!==confirm){

Swal.showValidationMessage(

"Passwords do not match."

);

return false;

}

return{

current_password:current,

new_password:password,

confirm_password:confirm

};

}

})

.then((result)=>{

if(!result.isConfirmed){

return;

}

fetch(

"ajax/change_password.php",

{

method:"POST",

credentials:"same-origin",

headers:{

"Content-Type":

"application/json",

"Accept":

"application/json"

},

body:JSON.stringify({

current_password:
result.value.current_password,

new_password:
result.value.new_password,

confirm_password:
result.value.confirm_password,

csrf_token:
examSphereCsrfToken

})

}

)

.then(res=>{

if(!res.ok){

throw new Error(
"Password request failed."
);

}

return res.json();

})

.then(data=>{

Swal.fire({

icon:data.status,

title:data.title,

text:data.message,

confirmButtonColor:"#556B2F"

});

})

.catch(()=>{

Swal.fire({

icon:"error",

title:"Server Error",

text:"Unable to update password."

});

});

});

});

}

/*==================================================
        SIMPLE TOAST
==================================================*/

function showToast(

icon,

title

){

Swal.fire({

toast:true,

position:"top-end",

icon:icon,

title:title,

showConfirmButton:false,

timer:2000,

timerProgressBar:true

});

}

/*==================================================
        END
==================================================*/

console.log(

"ExamSphere Profile Loaded"

);