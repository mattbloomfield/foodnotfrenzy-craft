import "../css/app.css";


if (import.meta.hot) {
    import.meta.hot.accept(() => {
        console.log('hot reload');
    });
}