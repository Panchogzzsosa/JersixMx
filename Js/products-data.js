const productsData = [
    {
        id: 1,
        name: "Real Madrid Local",
        team: "Real Madrid",
        category: "local",
        league: "laliga",
        price: "799.00",
        image: "../img/Jerseys/RealMadridLocal.jpg",
        url: "../Productos-equipos/producto-real-madrid",
        productId: "real_madrid"
    },
    {
        id: 2,
        name: "Barcelona Local",
        team: "Barcelona",
        category: "local",
        league: "laliga",
        price: "799.00",
        image: "../img/LoMasVendido/Barca.png",
        url: "../Productos-equipos/producto-barca",
        productId: "barcelona"
    },
    {
        id: 3,
        name: "Manchester City Local",
        team: "Manchester City",
        category: "local",
        league: "premier",
        price: "799.00",
        image: "../img/Jerseys/ManchesterCity.png",
        url: "../Productos-equipos/producto-manchester-city",
        productId: "manchester_city"
    },
    {
        id: 4,
        name: "Bayern Munich Local",
        team: "Bayern Munich",
        category: "local",
        league: "bundesliga",
        price: "799.00",
        image: "../img/Jerseys/BayerMunchenLocal.jpg",
        url: "../Productos-equipos/producto-bayer-munchen",
        productId: "bayern_munich"
    },
    {
        id: 5,
        name: "AC Milan Local",
        team: "AC Milan",
        category: "local",
        league: "serieA",
        price: "799.00",
        image: "../img/Jerseys/MilanLocal.png",
        url: "../Productos-equipos/producto-ac-milan",
        productId: "ac_milan"
    },
    {
        id: 6,
        name: "Paris Saint-Germain Local",
        team: "PSG",
        category: "local",
        league: "ligue1",
        price: "799.00",
        image: "../img/Jerseys/PSGLocal.jpg",
        url: "../Productos-equipos/producto-psg",
        productId: "psg"
    },
    {
        id: 7,
        name: "Rayados Local",
        team: "Rayados",
        category: "local",
        league: "ligamx",
        price: "799.00",
        image: "../img/Jerseys/RayadosLocal.jpg",
        url: "../Productos-equipos/producto-rayados",
        productId: "rayados"
    },
    {
        id: 8,
        name: "Tigres Local",
        team: "Tigres",
        category: "local",
        league: "ligamx",
        price: "799.00",
        image: "../img/Jerseys/TigresLocal.jpg",
        url: "../Productos-equipos/producto-tigres",
        productId: "tigres"
    },
    {
        id: 9,
        name: "América Local",
        team: "América",
        category: "local",
        league: "ligamx",
        price: "799.00",
        image: "../img/Jerseys/AmericaLocal.jpg",
        url: "../Productos-equipos/producto-america",
        productId: "america"
    },
    {
        id: 10,
        name: "Chivas Local",
        team: "Chivas",
        category: "local",
        league: "ligamx",
        price: "799.00",
        image: "../img/Jerseys/ChivasLocal.jpg",
        url: "../Productos-equipos/producto-chivas",
        productId: "chivas"
    },
    {
        id: 11,
        name: "Cruz Azul Local",
        team: "Cruz Azul",
        category: "local",
        league: "ligamx",
        price: "700.00",
        image: "../img/Jerseys/CruzAzulLocal.jpg",
        url: "../Productos-equipos/producto-cruzazul",
        productId: "cruz_azul"
    }
];

if (typeof module !== 'undefined' && module.exports) {
    module.exports = productsData;
}

// Function to update product prices
function updateProductPrices() {
    productsData.forEach(product => {
        fetch(`get_product_price.php?id=${product.productId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    product.price = data.price;
                    // Update price display in DOM
                    const priceElement = document.querySelector(`[data-product-id="${product.productId}"]`);
                    if (priceElement) {
                        priceElement.textContent = `$ ${parseFloat(data.price).toFixed(2)}`;
                    }
                }
            })
            .catch(error => console.error('Error fetching price:', error));
    });
}

// Update prices initially and every 30 seconds
document.addEventListener('DOMContentLoaded', function() {
    updateProductPrices();
    setInterval(updateProductPrices, 30000);
});