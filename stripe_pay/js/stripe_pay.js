// Set Stripe publishable key to initialize Stripe.js
var stripe = Stripe(drupalSettings.stripe_pk);
var amount = '';
var title = '';
var url = '';
var id = '';
var quantity = '1';
var parentElements = document.querySelectorAll('.stripe-pay-wrap');
if(parentElements) {
    parentElements.forEach(parentElement => {
        var payBtn = parentElement.querySelector('.payButton');
        var quantityInput = parentElement.querySelector('.quantity');

        if (payBtn) {
            // Payment request handler
            payBtn.addEventListener("click", function (evt) {
                 amount = payBtn.getAttribute("data-amount");
                 title = payBtn.getAttribute("data-title");
                 url = payBtn.getAttribute("data-url");
                 id = payBtn.getAttribute("data-id");
                 if(quantityInput) {
                    quantity = quantityInput.value;
                 }

                createCheckoutSession().then(function (data) {
                    if(data.sessionId){
                        stripe.redirectToCheckout({
                            sessionId: data.sessionId,
                        }).then(handleResult);
                    }else{
                        handleResult(data);
                    }
                });

            });
        } else {

        }
    });
}

// Create a Checkout Session with the selected product
var createCheckoutSession = function (stripe) {
    return fetch(drupalSettings.payment_init_url, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            createCheckoutSession: 1,
            amount: amount,
            title: title,
            url: url,
            id: id,
            quantity: quantity,
        }),
    }).then(function (result) {
        return result.json();
    });
};

// Handle any errors returned from Checkout
var handleResult = function (result) {
    if (result.error) {
        showMessage(result.error.message);
    }
};

// Display message
function showMessage(messageText) {
    var messageContainer = document.querySelector(".paymentResponse");
    if(messageContainer) {
        messageContainer.classList.remove("hidden");
        messageContainer.textContent = messageText;

        setTimeout(function () {
            messageContainer.classList.add("hidden");
            messageText.textContent = "Loading";
        }, 5000);
    }
}