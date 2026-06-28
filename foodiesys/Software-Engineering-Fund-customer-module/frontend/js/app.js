console.log("Customer Module Loaded");

/* ===========================
   CART FUNCTIONS
=========================== */

function getCart(){

    return JSON.parse(localStorage.getItem("cart")) || [];

}

function saveCart(cart){

    localStorage.setItem("cart",JSON.stringify(cart));

}

function addItemToCart(itemId,vendor,food,price,quantity){

    let cart=getCart();

    quantity=parseInt(quantity);

    let existing=cart.find(item=>item.itemId==itemId);

    if(existing){

        existing.quantity+=quantity;

    }else{

        cart.push({

            itemId:itemId,
            vendor:vendor,
            food:food,
            price:parseFloat(price),
            quantity:quantity

        });

    }

    saveCart(cart);

    localStorage.setItem("vendor",vendor);

    alert(food+" added to cart!");

    window.location.href="cart.html";

}

function quickAdd(itemId, vendor, food, price){

    let cart = getCart();

    itemId = parseInt(itemId);
    price = parseFloat(price);

    let existing = cart.find(item => item.itemId === itemId);

    if(existing){

        existing.quantity++;

    }else{

        cart.push({

            itemId: itemId,
            vendor: vendor,
            food: food,
            price: price,
            quantity: 1

        });

    }

    saveCart(cart);

    localStorage.setItem("vendor", vendor);

    alert(food + " added to cart!");

    window.location.href = "cart.html";

}
function renderCart(){

    let table=document.getElementById("cartData");

    if(!table) return;

    let cart=getCart();

    let total=0;

    let rows="";

    cart.forEach((item,index)=>{

        let subtotal=item.price*item.quantity;

        total+=subtotal;

        rows+=`

        <tr>

            <td>${item.vendor}</td>

            <td>${item.food}</td>

            <td>RM ${item.price.toFixed(2)}</td>

            <td>

                <button onclick="decreaseQuantity(${index})">-</button>

                ${item.quantity}

                <button onclick="increaseQuantity(${index})">+</button>

            </td>

            <td>RM ${subtotal.toFixed(2)}</td>

            <td>

                <button onclick="removeItem(${index})">

                    Remove

                </button>

            </td>

        </tr>

        `;

    });

    table.innerHTML=rows;

    let totalLabel=document.getElementById("totalAmount");

    if(totalLabel){

        totalLabel.innerText="Total : RM "+total.toFixed(2);

    }

    localStorage.setItem("cartTotal",total.toFixed(2));

}

function increaseQuantity(index){

    let cart=getCart();

    cart[index].quantity++;

    saveCart(cart);

    renderCart();

}

function decreaseQuantity(index){

    let cart=getCart();

    if(cart[index].quantity>1){

        cart[index].quantity--;

    }else{

        cart.splice(index,1);

    }

    saveCart(cart);

    renderCart();

}

function removeItem(index){

    let cart=getCart();

    cart.splice(index,1);

    saveCart(cart);

    renderCart();

}

function clearCart(){

    if(confirm("Clear all items?")){

        localStorage.removeItem("cart");

        renderCart();

    }

}
////////////////////////////////////
/* ===========================
   PICKUP FUNCTIONS
=========================== */

function savePickup(){

    let pickup=document.getElementById("pickupTime");

    if(!pickup){

        return;

    }

    if(pickup.value==""){

        alert("Please select a pickup time.");

        return;

    }

    localStorage.setItem("pickupTime",pickup.value);

    window.location.href="payment.html";

}

/* ===========================
   PAYMENT FUNCTIONS
=========================== */

function showQR(){

    let method=document.getElementById("paymentMethod");

    if(!method){

        return;

    }

    let qr=document.getElementById("qrSection");

    let button=document.getElementById("paymentButton");

    if(method.value=="tng"){

        qr.style.display="block";

        button.innerText="Confirm Payment";

    }else{

        qr.style.display="none";

        button.innerText="Place Order";

    }

}

function loadPayment(){

    let total=localStorage.getItem("cartTotal") || "0.00";

    let label=document.getElementById("paymentTotal");

    if(label){

        label.innerText="Total Payment : RM "+total;

    }

    showQR();

}

function confirmPayment(){

    let method = document.getElementById("paymentMethod").value;

    localStorage.setItem("paymentMethod", method);

    if(method == "Cash"){

        localStorage.setItem("paymentStatus","Pending Payment");

    }else{

        localStorage.setItem("paymentStatus","Paid");

    }

    localStorage.setItem("orderStatus","Preparing");

    localStorage.setItem("orderId","ORD"+Date.now());

    window.location.href="tracking.html";

}


///////////////////////////////////////////
/* ===========================
   TRACKING FUNCTIONS
=========================== */

function loadTracking(){

    let order=document.getElementById("orderId");
    let vendor=document.getElementById("vendor");
    let pickup=document.getElementById("pickupTime");
    let paymentMethod=document.getElementById("paymentMethod");
    let paymentStatus=document.getElementById("paymentStatus");
    let orderStatus=document.getElementById("orderStatus");
    let estimate=document.getElementById("estimate");

    if(order) order.innerText=localStorage.getItem("orderId") || "-";
    if(vendor) vendor.innerText=localStorage.getItem("vendor") || "-";
    if(pickup) pickup.innerText=localStorage.getItem("pickupTime") || "-";
    if(paymentMethod) paymentMethod.innerText=localStorage.getItem("paymentMethod") || "-";
    if(paymentStatus) paymentStatus.innerText=localStorage.getItem("paymentStatus") || "-";
    if(orderStatus) orderStatus.innerText=localStorage.getItem("orderStatus") || "-";
    if(estimate) estimate.innerText="15 Minutes";

}

/* ===========================
   HISTORY FUNCTIONS
=========================== */

function loadHistory(){

    let table=document.getElementById("historyData");

    if(!table) return;

    let cart=getCart();

    let total=0;

    let rows="";

    cart.forEach(item=>{

        let subtotal=item.price*item.quantity;

        total+=subtotal;

        rows+=`

        <tr>

            <td>${localStorage.getItem("orderId")}</td>
            <td>${item.vendor}</td>
            <td>${item.food}</td>
            <td>${item.quantity}</td>
            <td>RM ${subtotal.toFixed(2)}</td>
            <td>${localStorage.getItem("paymentStatus")}</td>
            <td>${localStorage.getItem("orderStatus")}</td>

        </tr>

        `;

    });

    table.innerHTML=rows;

    let grand=document.getElementById("grandTotal");

    if(grand){

        grand.innerText="Total : RM "+total.toFixed(2);

    }

    let pickup=document.getElementById("pickupTime");

    if(pickup){

        pickup.innerText=localStorage.getItem("pickupTime") || "-";

    }

}

/* ===========================
   REVIEW FUNCTIONS
=========================== */

function submitReview(){

    let rating=document.getElementById("rating").value;

    let comment=document.getElementById("comment").value.trim();

    if(comment==""){

        alert("Please enter your review.");

        return;

    }

    fetch("../php/addReview.php",{

        method:"POST",

        headers:{

            "Content-Type":"application/json"

        },

        body:JSON.stringify({

            order_id:localStorage.getItem("orderId"),
            vendor_id:localStorage.getItem("vendorId"),
            rating:rating,
            comment:comment

        })

    })

    .then(response=>response.json())

    .then(result=>{

        if(result.success){

            alert("Review submitted successfully.");

            window.location.href="history.html";

        }else{

            alert(result.message);

        }

    })

    .catch(error=>{

        console.log(error);

        alert("Failed to submit review.");

    });

}

/* ===========================
   RESET FUNCTIONS
=========================== */

function resetOrder(){

    localStorage.removeItem("cart");
    localStorage.removeItem("cartTotal");
    localStorage.removeItem("pickupTime");
    localStorage.removeItem("paymentMethod");
    localStorage.removeItem("paymentStatus");
    localStorage.removeItem("orderStatus");
    localStorage.removeItem("orderId");
    localStorage.removeItem("vendor");
    localStorage.removeItem("vendorId");

}