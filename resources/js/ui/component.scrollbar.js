/*
* Theme: Hyper - Responsive Bootstrap 5 Admin Dashboard
* Author: Coderthemes
* Component: Scrollbar Init Js
*/

$('.slimscroll-leftbar').slimscroll({
    height: 'auto',
    position: 'left',
    size: "4px",
    color: '#9ea5ab',
    wheelStep: 5,
    touchScrollStep: 20
});

$('.slimscroll-size').slimscroll({
    height: 'auto',
    position: 'right',
    size: "10px",
    color: '#9ea5ab',
    wheelStep: 5,
    touchScrollStep: 20
});

$('.slimscroll-color').slimscroll({
    height: 'auto',
    position: 'right',
    size: "5px",
    color: '#727cf5',
    wheelStep: 5,
    touchScrollStep: 20
});

$('.slimscroll-always-visible').slimscroll({
    height: 'auto',
    position: 'right',
    size: "5px",
    color: '#9ea5ab',
    wheelStep: 5,
    alwaysVisible: true,
    touchScrollStep: 20
});