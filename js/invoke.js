function registerScript() {
  if (typeof window.kkiapayisregistred == "undefined") {
    window.kkiapayisregistred = true;
    if (document.getElementById("kkiapay") != null) {
      document.getElementById("kkiapay").removeAttribute("onclick");
      document.getElementById("kkiapay").addEventListener("click", e => {
        e.preventDefault();
        addSuccessListener(response => {
          let input = document.createElement("input");
          let form = document.createElement("form");
          input.setAttribute("name", "transactionId");
          input.setAttribute("value", response.transactionId);
          form.appendChild(input);
          console.log(response);
          take_to_gateway("kkiapay");
        });
        openKkiapayWidget({
          amount: document.querySelector(".total").getAttribute("data-total"),
          key: "$key",
          sandbox: "$sandbox",
          theme: "$theme",
          text: "$text"
        });
        console.log("=====>");
      });
    }
  }
}
window.addEventListener("load", () => {
  registerScript();
});
