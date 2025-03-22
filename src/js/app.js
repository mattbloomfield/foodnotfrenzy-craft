import "../css/app.css";

console.log('hello world');

if (import.meta.hot) {
    import.meta.hot.accept((newModule) => {
        console.log('The module has been replaced:', newModule);
    });
}