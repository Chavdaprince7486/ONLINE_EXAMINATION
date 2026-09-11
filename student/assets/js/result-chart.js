// Chart JS will come later

const chart=document.getElementById("performanceChart");

if(chart){

new Chart(chart,{

type:"doughnut",

data:{

labels:[

"Correct",

"Wrong",

"Skipped"

],

datasets:[{

data:[

correctAnswers,

wrongAnswers,

skippedQuestions

],

backgroundColor:[

"#16A34A",

"#DC2626",

"#D97706"

],

borderWidth:0

}]

},

options:{

responsive:true,

cutout:"72%",

plugins:{

legend:{

position:"bottom"

}

}

}

});

}