(() => {

    'use strict';


    /*
    |--------------------------------------------------------------------------
    | DATA
    |--------------------------------------------------------------------------
    */

    const data =
        window.EXAMSPHERE_PERFORMANCE
        || {};


    const labels =
        Array.isArray(
            data.labels
        )
            ? data.labels
            : [];


    const values =
        Array.isArray(
            data.values
        )
            ? data.values.map(Number)
            : [];


    const titles =
        Array.isArray(
            data.titles
        )
            ? data.titles
            : [];


    /*
    |--------------------------------------------------------------------------
    | INITIALIZE
    |--------------------------------------------------------------------------
    */

    function initCharts(){

        if (
            typeof Chart ===
            'undefined'
        ){

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | SCORE PROGRESSION
        |--------------------------------------------------------------------------
        */

        const lineCanvas =
            document.getElementById(
                'scoreProgressChart'
            );


        if (
            lineCanvas
        ){

            new Chart(
                lineCanvas,
                {

                    type:
                        'line',


                    data:{

                        labels,

                        datasets:[

                            {

                                label:
                                    'Score',

                                data:
                                    values,

                                borderColor:
                                    '#556B2F',

                                backgroundColor:
                                    'rgba(85,107,47,.09)',

                                borderWidth:
                                    3,

                                fill:
                                    true,

                                tension:
                                    .38,

                                pointRadius:
                                    5,

                                pointHoverRadius:
                                    7,

                                pointBorderWidth:
                                    2,

                                pointBackgroundColor:
                                    '#FFFFFF',

                                pointBorderColor:
                                    '#556B2F'

                            }

                        ]

                    },


                    options:{

                        responsive:
                            true,

                        maintainAspectRatio:
                            false,

                        interaction:{

                            intersect:
                                false,

                            mode:
                                'index'

                        },


                        scales:{

                            x:{

                                grid:{

                                    display:
                                        false

                                },


                                ticks:{

                                    color:
                                        '#867C74',

                                    font:{

                                        family:
                                            'Poppins',

                                        size:
                                            11,

                                        weight:
                                            '600'

                                    }

                                },


                                border:{

                                    display:
                                        false

                                }

                            },


                            y:{

                                min:
                                    0,

                                max:
                                    100,

                                ticks:{

                                    stepSize:
                                        20,

                                    color:
                                        '#867C74',

                                    callback(
                                        value
                                    ){

                                        return (
                                            value +
                                            '%'
                                        );

                                    },


                                    font:{

                                        family:
                                            'Poppins',

                                        size:
                                            10,

                                        weight:
                                            '600'

                                    }

                                },


                                grid:{

                                    color:
                                        'rgba(93,64,55,.08)',

                                    drawBorder:
                                        false

                                },


                                border:{

                                    display:
                                        false

                                }

                            }

                        },


                        plugins:{

                            legend:{

                                display:
                                    false

                            },


                            tooltip:{

                                displayColors:
                                    false,

                                backgroundColor:
                                    '#3E2723',

                                titleColor:
                                    '#FFFFFF',

                                bodyColor:
                                    '#FFFFFF',

                                padding:
                                    12,

                                cornerRadius:
                                    10,


                                callbacks:{

                                    title(
                                        items
                                    ){

                                        const index =
                                            items?.[0]
                                                ?.dataIndex
                                            ?? 0;


                                        return (
                                            titles[index]
                                            ||
                                            labels[index]
                                            ||
                                            'Exam'
                                        );

                                    },


                                    label(
                                        context
                                    ){

                                        return (
                                            'Score: ' +
                                            Number(
                                                context.raw
                                                ||
                                                0
                                            ).toFixed(
                                                2
                                            ) +
                                            '%'
                                        );

                                    }

                                }

                            }

                        }

                    }

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | ANSWER ANALYSIS
        |--------------------------------------------------------------------------
        */

        const doughnutCanvas =
            document.getElementById(
                'answerAnalysisChart'
            );


        if (
            doughnutCanvas
        ){

            new Chart(
                doughnutCanvas,
                {

                    type:
                        'doughnut',


                    data:{

                        labels:[

                            'Correct',

                            'Wrong',

                            'Unanswered'

                        ],


                        datasets:[

                            {

                                data:[

                                    Number(
                                        data.correct
                                        ||
                                        0
                                    ),

                                    Number(
                                        data.wrong
                                        ||
                                        0
                                    ),

                                    Number(
                                        data.unanswered
                                        ||
                                        0
                                    )

                                ],


                                backgroundColor:[

                                    '#556B2F',

                                    '#A84538',

                                    '#B99B63'

                                ],


                                borderWidth:
                                    0,

                                hoverOffset:
                                    6

                            }

                        ]

                    },


                    options:{

                        responsive:
                            true,

                        maintainAspectRatio:
                            false,

                        cutout:
                            '76%',


                        plugins:{

                            legend:{

                                display:
                                    false

                            },


                            tooltip:{

                                displayColors:
                                    false,

                                backgroundColor:
                                    '#3E2723',

                                titleColor:
                                    '#FFFFFF',

                                bodyColor:
                                    '#FFFFFF',

                                padding:
                                    11,

                                cornerRadius:
                                    10

                            }

                        }

                    }

                }
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | DOM READY
    |--------------------------------------------------------------------------
    */

    if (
        document.readyState ===
        'loading'
    ){

        document.addEventListener(
            'DOMContentLoaded',
            initCharts,
            {
                once:
                    true
            }
        );

    } else {

        initCharts();

    }


})();