/*==================================================
        EXAMSPHERE STUDENT NAVIGATION
        NOTIFICATIONS
==================================================*/

(() => {

"use strict";

/*==================================================
        ELEMENTS
==================================================*/

const bell =
document.getElementById(
"notificationBell"
);

const dropdown =
document.getElementById(
"notificationDropdown"
);

const body =
document.getElementById(
"notificationBody"
);

const badge =
document.getElementById(
"notificationCount"
);

const csrfToken =
document.querySelector(
'meta[name="csrf-token"]'
)?.getAttribute(
"content"
) || "";

/*==================================================
        SAFETY CHECK
==================================================*/

if(

!bell ||
!dropdown ||
!body ||
!badge

){

return;

}

/*==================================================
        URLS
==================================================*/

const notificationUrl =
"ajax/load_notifications.php";

const readUrl =
"ajax/mark_notification_read.php";

/*==================================================
        REQUEST LOCK
==================================================*/

let refreshInProgress=false;

/*==================================================
        REFRESH NOTIFICATIONS
==================================================*/

const refreshNotifications =
async function(){

if(refreshInProgress){

return;

}

refreshInProgress=true;

try{

const response=
await fetch(

notificationUrl,

{

method:"GET",

cache:"no-store",

credentials:"same-origin",

headers:{

"Accept":
"application/json"

}

}

);

if(!response.ok){

throw new Error(
"Unable to load notifications."
);

}

const data=
await response.json();

if(

!data ||
data.status!=="success"

){

throw new Error(
data?.message ||
"Unable to load notifications."
);

}

/*==================================================
        UNREAD COUNT
==================================================*/

const unread=
Math.max(

0,

Number(
data.unread_count || 0
)

);

badge.textContent=
String(unread);

badge.style.display=
unread>0
?"flex"
:"none";

/*==================================================
        BODY
==================================================*/

body.innerHTML=
data.html ||
'<div class="notification-loading">No notifications found.</div>';

}catch(error){

console.error(
"ExamSphere notification error:",
error
);

body.innerHTML=
'<div class="notification-loading">Unable to load notifications.</div>';

}finally{

refreshInProgress=false;

}

};

/*==================================================
        OPEN / CLOSE DROPDOWN
==================================================*/

bell.addEventListener(

"click",

function(event){

event.preventDefault();

event.stopPropagation();

dropdown.classList.toggle(
"show"
);

if(

dropdown.classList.contains(
"show"
)

){

refreshNotifications();

}

});

/*==================================================
        KEEP DROPDOWN OPEN INSIDE
==================================================*/

dropdown.addEventListener(

"click",

function(event){

event.stopPropagation();

});

/*==================================================
        CLOSE OUTSIDE
==================================================*/

document.addEventListener(

"click",

function(){

dropdown.classList.remove(
"show"
);

});

/*==================================================
        MARK AS READ
==================================================*/

document.addEventListener(

"click",

async function(event){

const item=
event.target.closest(
".notification-item"
);

if(

!item ||
!dropdown.contains(item)

){

return;

}

event.preventDefault();
event.stopPropagation();

const notificationId=
item.dataset.id || "";

const destination=
item.getAttribute(
"href"
) || "#";

if(notificationId){

try{

const response=
await fetch(

readUrl,

{

method:"POST",

credentials:"same-origin",

headers:{

"Content-Type":
"application/x-www-form-urlencoded; charset=UTF-8",

"Accept":
"application/json"

},

body:

new URLSearchParams({

notification_id:
notificationId,

csrf_token:
csrfToken

}).toString()

}

);

if(!response.ok){

throw new Error(
"Unable to mark notification."
);

}

const data=
await response.json();

if(

data &&
data.status!=="success"

){

throw new Error(
data.message ||
"Unable to mark notification."
);

}

}catch(error){

console.error(

"ExamSphere notification read error:",

error

);

}

}

/*==================================================
        NAVIGATE
==================================================*/

if(

destination !== "#"

){

window.location.assign(
destination
);

}else{

item.classList.remove(
"unread"
);

refreshNotifications();

}

});

/*==================================================
        FIRST LOAD
==================================================*/

refreshNotifications();

/*==================================================
        AUTO REFRESH
==================================================*/

window.setInterval(

refreshNotifications,

30000

);

})();