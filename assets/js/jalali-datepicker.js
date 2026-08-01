/*
|--------------------------------------------------------------------------
| FormDesk Jalali Datepicker
| انتخابگر تاریخ شمسی سبک، بدون وابستگی به هیچ کتابخانه خارجی
| دارای انتخاب روز / ماه / سال
| الگوریتم تبدیل میلادی<->شمسی: jalaali (Borkowski)
|--------------------------------------------------------------------------
*/

(function () {

    function div(a, b) {
        return ~~(a / b);
    }

    function mod(a, b) {
        return a - ~~(a / b) * b;
    }


    var breaks = [
        -61, 9, 38, 199, 426,
        686, 756, 818, 1111,
        1181, 1210, 1635,
        2060, 2097, 2192,
        2262, 2324, 2394,
        2456, 3178
    ];


    function jalCal(jy) {

        var bl = breaks.length;
        var gy = jy + 621;
        var leapJ = -14;
        var jp = breaks[0];
        var jm;
        var jump;
        var leap;
        var leapG;
        var march;
        var n;
        var i;


        for (i = 1; i < bl; i++) {

            jm = breaks[i];
            jump = jm - jp;

            if (jy < jm)
                break;

            leapJ += div(jump, 33) * 8 +
                div(mod(jump, 33), 4);

            jp = jm;
        }


        n = jy - jp;

        leapJ += div(n, 33) * 8 +
            div(mod(n, 33) + 3, 4);


        if (mod(jump, 33) === 4 &&
            jump - n === 4) {

            leapJ++;
        }


        leapG =
            div(gy, 4) -
            div((div(gy, 100) + 1) * 3, 4) -
            150;


        march = 20 + leapJ - leapG;


        if (jump - n < 6) {

            n = n - jump +
                div(jump, 33) * 33;
        }


        leap = mod(mod(n + 1, 33) - 1, 4);


        if (leap === -1)
            leap = 4;


        return {
            leap: leap,
            gy: gy,
            march: march
        };
    }


    function g2d(gy, gm, gd) {

        var d =
            div(
                (gy + div(gm - 8, 6) + 100100) * 1461,
                4
            )
            +
            div(
                153 * mod(gm + 9, 12) + 2,
                5
            )
            +
            gd -
            34840408;


        d =
            d -
            div(
                div(
                    gy + 100100 + div(gm - 8, 6),
                    100
                ) * 3,
                4
            )
            +
            752;


        return d;
    }


    function d2g(jdn) {

        var j;
        var i;
        var gd;
        var gm;
        var gy;


        j = 4 * jdn + 139361631;

        j =
            j +
            div(
                div(4 * jdn + 183187720, 146097) * 3,
                4
            ) * 4 -
            3908;


        i =
            div(mod(j, 1461), 4) * 5 +
            308;


        gd =
            div(mod(i, 153), 5) + 1;


        gm =
            mod(div(i, 153), 12) + 1;


        gy =
            div(j, 1461) -
            100100 +
            div(8 - gm, 6);


        return {
            gy: gy,
            gm: gm,
            gd: gd
        };
    }


    function j2d(jy, jm, jd) {

        var r = jalCal(jy);

        return (
            g2d(r.gy, 3, r.march)
            +
            (jm - 1) * 31
            -
            div(jm, 7) * (jm - 7)
            +
            jd - 1
        );
    }


    function d2j(jdn) {

        var gy = d2g(jdn).gy;
        var jy = gy - 621;

        var r = jalCal(jy);

        var jdn1f =
            g2d(
                gy,
                3,
                r.march
            );


        var jd;
        var jm;
        var k =
            jdn - jdn1f;



        if (k >= 0) {

            if (k <= 185) {

                jm = 1 + div(k, 31);
                jd = mod(k, 31) + 1;

                return {
                    jy: jy,
                    jm: jm,
                    jd: jd
                };
            }

            k -= 186;

        } else {

            jy--;

            k += 179;

            if (r.leap === 1)
                k++;
        }


        jm = 7 + div(k, 30);

        jd = mod(k, 30) + 1;


        return {
            jy: jy,
            jm: jm,
            jd: jd
        };
    }
    function toJalaali(gy, gm, gd) {
        return d2j(g2d(gy, gm, gd));
    }


    function jalaaliMonthLength(jy, jm) {

        if (jm <= 6)
            return 31;

        if (jm <= 11)
            return 30;

        return jalCal(jy).leap === 0 ? 30 : 29;
    }


    var monthNames = [
        'فروردین',
        'اردیبهشت',
        'خرداد',
        'تیر',
        'مرداد',
        'شهریور',
        'مهر',
        'آبان',
        'آذر',
        'دی',
        'بهمن',
        'اسفند'
    ];


    var weekDays = [
        'ش',
        'ی',
        'د',
        'س',
        'چ',
        'پ',
        'ج'
    ];


    function pad(n) {
        return (n < 10 ? '0' : '') + n;
    }



    function buildCalendar(input) {


        var today = new Date();


        var todayJalali = toJalaali(
            today.getFullYear(),
            today.getMonth() + 1,
            today.getDate()
        );


        var current = {

            jy: todayJalali.jy,
            jm: todayJalali.jm

        };



        if (input.value) {


            var parts = input.value.split('/');


            if (parts.length === 3) {

                current.jy =
                    parseInt(parts[0], 10);

                current.jm =
                    parseInt(parts[1], 10);
            }
        }



        var panel = document.createElement('div');

        panel.className =
            'fd-jalali-panel';



        var view = 'days';


        var yearBlockStart =
            current.jy - 11;



        function render() {


            panel.innerHTML = '';



            var header =
                document.createElement('div');


            header.className =
                'fd-jalali-header';



            var prev =
                document.createElement('button');


            prev.type = 'button';

            prev.className =
                'fd-jalali-nav';


            prev.textContent = '‹';



            var next =
                document.createElement('button');


            next.type = 'button';

            next.className =
                'fd-jalali-nav';


            next.textContent = '›';




            var title =
                document.createElement('span');


            title.className =
                'fd-jalali-title';





            if (view === 'days') {



                var monthLabel =
                    document.createElement('span');


                monthLabel.className =
                    'fd-jalali-label';


                monthLabel.textContent =
                    monthNames[current.jm - 1];



                var yearLabel =
                    document.createElement('span');


                yearLabel.className =
                    'fd-jalali-label';


                yearLabel.textContent =
                    current.jy;




                monthLabel.addEventListener(
                    'click',
                    function (e) {

                        e.stopPropagation();

                        view = 'months';

                        render();

                    }
                );



                yearLabel.addEventListener(
                    'click',
                    function (e) {

                        e.stopPropagation();

                        yearBlockStart =
                            current.jy - 11;


                        view = 'years';

                        render();

                    }
                );



                title.appendChild(monthLabel);


                title.appendChild(
                    document.createTextNode(' ')
                );


                title.appendChild(yearLabel);



            }



            else if (view === 'months') {


                title.textContent =
                    current.jy;

            }



            else if (view === 'years') {


                title.textContent =
                    yearBlockStart +
                    ' - ' +
                    (yearBlockStart + 19);

            }




            header.appendChild(next);

            header.appendChild(title);

            header.appendChild(prev);


            panel.appendChild(header);





            if (view === 'days') {



                var weekRow =
                    document.createElement('div');


                weekRow.className =
                    'fd-jalali-week';



                weekDays.forEach(function (day) {


                    var cell =
                        document.createElement('span');


                    cell.textContent =
                        day;


                    weekRow.appendChild(cell);


                });



                panel.appendChild(weekRow);




                var grid =
                    document.createElement('div');


                grid.className =
                    'fd-jalali-grid';



                var g =
                    d2g(
                        j2d(
                            current.jy,
                            current.jm,
                            1
                        )
                    );



                var jsDate =
                    new Date(
                        g.gy,
                        g.gm - 1,
                        g.gd
                    );



                var weekDay =
                    (jsDate.getDay() + 1) % 7;





                for (
                    var i = 0;
                    i < weekDay;
                    i++
                ) {

                    grid.appendChild(
                        document.createElement('span')
                    );

                }

                var monthLength =
                    jalaaliMonthLength(
                        current.jy,
                        current.jm
                    );



                for (
                    var day = 1;
                    day <= monthLength;
                    day++
                ) {


                    (function (day) {


                        var cell =
                            document.createElement('button');


                        cell.type = 'button';


                        cell.className =
                            'fd-jalali-day';


                        cell.textContent =
                            day;



                        if (
                            current.jy === todayJalali.jy &&
                            current.jm === todayJalali.jm &&
                            day === todayJalali.jd
                        ) {

                            cell.classList.add('today');

                        }



                        cell.addEventListener(
                            'click',
                            function (e) {


                                e.stopPropagation();


                                input.value =
                                    current.jy +
                                    '/' +
                                    pad(current.jm) +
                                    '/' +
                                    pad(day);



                                closePanel();

                            }
                        );



                        grid.appendChild(cell);



                    })(day);

                }



                panel.appendChild(grid);



            }



            else if (view === 'months') {



                var monthsGrid =
                    document.createElement('div');


                monthsGrid.className =
                    'fd-jalali-grid fd-jalali-months-grid';



                monthNames.forEach(function (name, index) {



                    var cell =
                        document.createElement('button');


                    cell.type =
                        'button';


                    cell.className =
                        'fd-jalali-month-cell';


                    cell.textContent =
                        name;




                    if (index + 1 === current.jm) {

                        cell.classList.add('active');

                    }




                    cell.addEventListener(
                        'click',
                        function (e) {


                            e.stopPropagation();


                            current.jm =
                                index + 1;


                            view =
                                'days';


                            render();


                        }
                    );



                    monthsGrid.appendChild(cell);



                });



                panel.appendChild(monthsGrid);



            }



            else if (view === 'years') {



                var yearsGrid =
                    document.createElement('div');


                yearsGrid.className =
                    'fd-jalali-grid fd-jalali-years-grid';




                for (
                    var y = yearBlockStart;
                    y < yearBlockStart + 20;
                    y++
                ) {



                    (function (y) {



                        var cell =
                            document.createElement('button');



                        cell.type =
                            'button';



                        cell.className =
                            'fd-jalali-year-cell';



                        cell.textContent =
                            y;




                        if (y === current.jy) {

                            cell.classList.add('active');

                        }




                        cell.addEventListener(
                            'click',
                            function (e) {


                                e.stopPropagation();


                                current.jy =
                                    y;


                                view =
                                    'months';


                                render();



                            }
                        );



                        yearsGrid.appendChild(cell);



                    })(y);



                }



                panel.appendChild(yearsGrid);



            }





            prev.addEventListener(
                'click',
                function (e) {


                    e.stopPropagation();



                    if (view === 'days') {


                        current.jm--;


                        if (current.jm < 1) {


                            current.jm = 12;

                            current.jy--;

                        }


                    }


                    else if (view === 'months') {


                        current.jy--;


                    }


                    else if (view === 'years') {


                        yearBlockStart -= 20;


                    }



                    render();



                }
            );





            next.addEventListener(
                'click',
                function (e) {


                    e.stopPropagation();



                    if (view === 'days') {


                        current.jm++;


                        if (current.jm > 12) {


                            current.jm = 1;

                            current.jy++;

                        }


                    }


                    else if (view === 'months') {


                        current.jy++;


                    }


                    else if (view === 'years') {


                        yearBlockStart += 20;


                    }



                    render();



                }
            );



        }



        render();



        return panel;



    }





    var activePanel = null;



    function closePanel() {


        if (activePanel) {


            activePanel.remove();


            activePanel = null;


        }

    }





    document.addEventListener(
        'click',
        function (e) {



            if (
                e.target.classList &&
                e.target.classList.contains(
                    'fd-jalali-date'
                )
            ) {



                if (
                    activePanel &&
                    activePanel.dataset.forInput ===
                    e.target.name
                ) {


                    closePanel();

                    return;

                }




                closePanel();




                var panel =
                    buildCalendar(e.target);



                panel.dataset.forInput =
                    e.target.name;



                e.target.parentNode.style.position =
                    'relative';



                e.target.parentNode.appendChild(panel);



                activePanel =
                    panel;



            }



            else if (
                activePanel &&
                !activePanel.contains(e.target)
            ) {


                closePanel();

            }



        }
    );



})();