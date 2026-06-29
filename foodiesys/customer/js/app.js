console.log("Customer Module Loaded");

/* ===========================
   VENDOR / MENU BROWSING
=========================== */

function loadVendors(){

    let container = document.getElementById("vendorContainer");

    if(!container) return;

    fetch("../php/getVendors.php")

    .then(response => response.json())

    .then(result => {

        if(!result.success || !result.vendors.length){

            container.innerHTML = "<p>No vendors available right now.</p>";

            return;

        }

        let html = "";

        result.vendors.forEach(vendor => {

            let isOpen = vendor.stall_status === "Open";

            html += `

            <div class="vendor-card">

                ${vendor.image_url
                    ? `<img src="${vendor.image_url}" class="vendor-img">`
                    : `<div class="vendor-img" style="display:flex;align-items:center;justify-content:center;background:#cfd9ea;color:#555;font-size:12px;">No Photo</div>`
                }

                <h3>${vendor.stall_name}</h3>

                <p class="${isOpen ? "status-open" : "status-closed"}">Status: ${vendor.stall_status}</p>

                <p>${vendor.operating_hours || ""}</p>

                ${isOpen
                    ? `<a href="menu.html?vendor_id=${vendor.vendor_id}"><button>Select</button></a>`
                    : `<button disabled>Vendor Currently Unavailable</button>`
                }

            </div>

            `;

        });

        container.innerHTML = html;

    })

    .catch(error => {

        console.log(error);

        container.innerHTML = "<p>Unable to load vendors.</p>";

    });

}

function loadMenu(){

    let container = document.getElementById("menuContainer");

    if(!container) return;

    let params = new URLSearchParams(window.location.search);
    let vendorId = params.get("vendor_id");

    if(!vendorId){

        container.innerHTML = "<p>No vendor selected.</p>";

        return;

    }

    fetch("../php/getVendors.php")

    .then(response => response.json())

    .then(result => {

        let vendor = (result.vendors || []).find(v => String(v.vendor_id) === String(vendorId));

        let title = document.getElementById("vendorTitle");
        let photo = document.getElementById("vendorPhoto");

        if(title) title.innerText = vendor ? vendor.stall_name : "Menu";

        if(photo){

            if(vendor && vendor.image_url){

                photo.src = vendor.image_url;
                photo.style.display = "inline-block";

            } else {

                photo.style.display = "none";

            }

        }

        if(vendor && vendor.stall_status !== "Open"){

            container.innerHTML = "<p>Vendor Currently Unavailable</p>";

            return;

        }

        loadMenuItems(vendorId, vendor ? vendor.stall_name : "Vendor");

    });

}

function loadMenuItems(vendorId, vendorName){

    let container = document.getElementById("menuContainer");

    fetch("../php/getItems.php?vendor_id=" + encodeURIComponent(vendorId))

    .then(response => response.json())

    .then(result => {

        if(!result.success || !result.items.length){

            container.innerHTML = "<p>No items available from this vendor yet.</p>";

            return;

        }

        let html = "";

        result.items.forEach(item => {

            html += `

            <div class="food-card">

                ${item.image_url ? `<img src="${item.image_url}" class="food-img">` : ""}

                <h3>${item.item_name}</h3>

                <p>${item.item_desc || ""}</p>

                <p class="price">RM ${item.price.toFixed(2)}</p>

                <button onclick="quickAdd(${item.item_id}, ${vendorId}, '${vendorName.replace(/'/g, "\\'")}', '${item.item_name.replace(/'/g, "\\'")}', ${item.price})">
                    Add to Cart
                </button>

            </div>

            `;

        });

        container.innerHTML = html;

    })

    .catch(error => {

        console.log(error);

        container.innerHTML = "<p>Unable to load menu.</p>";

    });

}

/* ===========================
   CART FUNCTIONS
=========================== */

function getCart(){

    return JSON.parse(localStorage.getItem("cart")) || [];

}

function saveCart(cart){

    localStorage.setItem("cart",JSON.stringify(cart));

}

function addItemToCart(itemId,vendorId,vendor,food,price,quantity){

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
    localStorage.setItem("vendorId",vendorId);

    alert(food+" added to cart!");

    window.location.href="cart.html";

}

function quickAdd(itemId, vendorId, vendor, food, price){

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
    localStorage.setItem("vendorId", vendorId);

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

function loadTimeSlots(){

    let select = document.getElementById("pickupTime");

    if(!select) return;

    fetch("../php/getTimeSlots.php")

    .then(response => response.json())

    .then(result => {

        if(!result.success || !result.slots.length){

            select.innerHTML = `<option value="">No time slots available</option>`;

            return;

        }

        let html = `<option value="">Choose Pickup Time</option>`;

        result.slots.forEach(slot => {

            html += `<option value="${slot.timeslot_id}" data-label="${slot.label}">${slot.label}</option>`;

        });

        select.innerHTML = html;

    })

    .catch(error => {

        console.log(error);

        select.innerHTML = `<option value="">Unable to load time slots</option>`;

    });

}

function savePickup(){

    let pickup=document.getElementById("pickupTime");

    if(!pickup){

        return;

    }

    if(pickup.value==""){

        alert("Please select a pickup time.");

        return;

    }

    let label = pickup.options[pickup.selectedIndex].getAttribute("data-label") || pickup.options[pickup.selectedIndex].text;

    localStorage.setItem("timeslotId",pickup.value);
    localStorage.setItem("pickupTime",label);

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

    let cart = getCart();

    if(cart.length === 0){

        alert("Your cart is empty.");

        window.location.href="vendors.html";

        return;

    }

    let methodSelect = document.getElementById("paymentMethod").value;
    let paymentMethod = methodSelect === "tng" ? "QR Code" : "Cash";

    // QR Code payments need an explicit Yes/No confirmation before the order
    // is marked paid; Cash orders are settled at pickup so they skip this.
    if(paymentMethod === "QR Code"){

        let confirmed = confirm("Have you completed the payment?");

        if(!confirmed){

            // Order stays unplaced; customer can retry payment from this page.
            return;

        }

    }

    let vendorId = localStorage.getItem("vendorId");
    let timeslotId = localStorage.getItem("timeslotId");

    let button = document.getElementById("paymentButton");
    if(button) button.disabled = true;

    fetch("../php/addOrder.php", {

        method: "POST",

        headers: {

            "Content-Type": "application/json"

        },

        body: JSON.stringify({

            vendor_id: vendorId,
            timeslot_id: timeslotId,
            payment_method: paymentMethod,
            cart: cart

        })

    })

    .then(response => response.json())

    .then(result => {

        if(!result.success){

            alert(result.message || "Payment failed");

            if(button) button.disabled = false;

            if(result.message && result.message.indexOf("time slot") !== -1){

                window.location.href = "pickup.html";

            }

            return;

        }

        localStorage.setItem("lastOrderId", result.order_id);
        localStorage.setItem("lastVendorId", vendorId);

        localStorage.removeItem("cart");
        localStorage.removeItem("cartTotal");

        window.location.href = "tracking.html?order_id=" + result.order_id;

    })

    .catch(error => {

        console.log(error);

        alert("Payment failed");

        if(button) button.disabled = false;

    });

}


///////////////////////////////////////////
/* ===========================
   TRACKING FUNCTIONS
=========================== */

function loadTracking(){

    let params = new URLSearchParams(window.location.search);
    let orderId = params.get("order_id") || localStorage.getItem("lastOrderId");

    let order=document.getElementById("orderId");

    if(orderId){

        fetchAndRenderTracking(orderId);
        return;

    }

    // No order in mind yet (e.g. came straight from the home page) - fall
    // back to the customer's most recent order.
    fetch("../php/getOrderHistory.php")

    .then(response => response.json())

    .then(result => {

        if(result.success && result.orders.length){

            fetchAndRenderTracking(result.orders[0].order_id);

        } else if(order) {

            order.innerText = "No orders yet.";

        }

    })

    .catch(() => {

        if(order) order.innerText = "-";

    });

}

function fetchAndRenderTracking(orderId){

    let order=document.getElementById("orderId");
    let vendor=document.getElementById("vendor");
    let pickup=document.getElementById("pickupTime");
    let paymentMethod=document.getElementById("paymentMethod");
    let paymentStatus=document.getElementById("paymentStatus");
    let orderStatus=document.getElementById("orderStatus");
    let progress=document.getElementById("progress");

    fetch("../php/getOrderStatus.php?order_id=" + encodeURIComponent(orderId))

    .then(response => response.json())

    .then(result => {

        if(!result.success){

            if(order) order.innerText = "Not found";

            return;

        }

        let o = result.order;

        if(order) order.innerText = "ORD" + String(o.order_id).padStart(5, "0");
        if(vendor) vendor.innerText = o.stall_name || "-";
        if(pickup) pickup.innerText = o.pickup_time || "-";
        if(paymentMethod) paymentMethod.innerText = o.payment_method || "-";
        if(paymentStatus) paymentStatus.innerText = o.payment_status || "-";
        if(orderStatus) orderStatus.innerText = o.order_status || "-";

        if(progress){

            let steps = ["Paid", "Preparing", "Ready for Pickup", "Completed"];
            let currentIndex = steps.indexOf(o.order_status);

            let lines = [
                "[X] Order Placed",
                (currentIndex >= 0 ? "[X]" : "[ ]") + " Payment Confirmed",
                (currentIndex >= 1 ? "[X]" : "[ ]") + " Preparing Food",
                (currentIndex >= 2 ? "[X]" : "[ ]") + " Ready For Pickup",
                (currentIndex >= 3 ? "[X]" : "[ ]") + " Completed"
            ];

            if(o.order_status === "Cancelled"){

                lines = ["[X] Order Placed", "[X] Cancelled"];

            }

            progress.innerText = lines.join("\n\n");

        }

    })

    .catch(error => {

        console.log(error);

        if(order) order.innerText = "Unable to load order.";

    });

}

/* ===========================
   HISTORY FUNCTIONS
=========================== */

function loadHistory(){

    let table=document.getElementById("historyData");

    if(!table) return;

    fetch("../php/getOrderHistory.php")

    .then(response => response.json())

    .then(result => {

        if(!result.success || !result.orders.length){

            table.innerHTML = `<tr><td colspan="7">No past orders found.</td></tr>`;

            return;

        }

        let rows = "";

        result.orders.forEach(order => {

            let paddedId = "ORD" + String(order.order_id).padStart(5, "0");

            if(!order.items.length){

                rows += `

                <tr>
                    <td>${paddedId}</td>
                    <td>${order.stall_name}</td>
                    <td colspan="2">No items recorded</td>
                    <td>RM ${(order.amount || 0).toFixed(2)}</td>
                    <td>${order.payment_status || "-"}</td>
                    <td>${order.order_status}
                        <br><a href="review.html?order_id=${order.order_id}&vendor_id=${order.vendor_id}">Leave Review</a>
                    </td>
                </tr>

                `;

                return;

            }

            order.items.forEach((item, idx) => {

                rows += `

                <tr>
                    <td>${idx === 0 ? paddedId : ""}</td>
                    <td>${idx === 0 ? order.stall_name : ""}</td>
                    <td>${item.item_name}</td>
                    <td>${item.quantity}</td>
                    <td>RM ${parseFloat(item.subtotal).toFixed(2)}</td>
                    <td>${idx === 0 ? (order.payment_status || "-") : ""}</td>
                    <td>${idx === 0 ? order.order_status + ` <br><a href="review.html?order_id=${order.order_id}&vendor_id=${order.vendor_id}">Leave Review</a>` : ""}</td>
                </tr>

                `;

            });

        });

        table.innerHTML = rows;

        let grand=document.getElementById("grandTotal");

        if(grand){

            let total = result.orders.reduce((sum, o) => sum + (o.amount || 0), 0);

            grand.innerText="Total : RM "+total.toFixed(2);

        }

    })

    .catch(error => {

        console.log(error);

        table.innerHTML = `<tr><td colspan="7">Unable to load orders. Please try again.</td></tr>`;

    });

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

    let params = new URLSearchParams(window.location.search);

    let orderId = params.get("order_id") || localStorage.getItem("lastOrderId");
    let vendorId = params.get("vendor_id") || localStorage.getItem("lastVendorId");

    fetch("../php/addReview.php",{

        method:"POST",

        headers:{

            "Content-Type":"application/json"

        },

        body:JSON.stringify({

            order_id:orderId,
            vendor_id:vendorId,
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
    localStorage.removeItem("timeslotId");
    localStorage.removeItem("lastOrderId");
    localStorage.removeItem("lastVendorId");
    localStorage.removeItem("vendor");
    localStorage.removeItem("vendorId");

}
