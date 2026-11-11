const buttons = document.querySelectorAll(".distribute-btn");

const message = document.getElementById("message");

buttons.forEach(button => {

    button.addEventListener("click", function () {

        const stubId = this.dataset.stubId;

        this.disabled = true;

        fetch("update_stub.php", {
            method: "POST",

            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },

            body: "stub_id=" + encodeURIComponent(stubId)
        })

        .then(response => {
            return response.json().then(data => ({
                ok: response.ok,
                data: data
            }));
        })

        .then(result => {

            if (result.ok && result.data.success) {

                const row = document.querySelector(
                    `[data-stub-row="${stubId}"]`
                );

                const status = row.querySelector(".status span");

                status.textContent = "Distributed";
                status.className = "distributed";

                button.textContent = "Already Distributed";

                button.disabled = true;

                message.textContent = "Stub successfully distributed.";
                message.style.color = "green";

            } else {

                message.textContent = result.data.message;
                message.style.color = "red";

                button.disabled = false;
            }

        })

        .catch(error => {

            console.error("Error:", error);

            message.textContent = "Unable to connect to the server.";
            message.style.color = "red";

            button.disabled = false;

        });

    });

});
