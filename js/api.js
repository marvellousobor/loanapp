const BASE = "/loanapp/api.php?endpoint=";

// Default POST bodies per endpoint
const defaults = {
  create_user: {
    name: "John Doe",
    email: "john@gmail.com",
    password: "Test@1234",
    monthly_income: 150000,
  },
  apply: { user_id: 1, amount: 50000, purpose: "Business expansion" },
  update_loan: { loan_id: 1, status: "approved" },
  update_config: {
    max_loan_percent: 50,
    min_loan_amount: 5000,
    max_loan_amount: 5000000,
  },
};

// Endpoints that need an ?id= param
const needsId = ["user", "loan"];
// Endpoints that need ?user_id= param
const needsUserId = ["wallet"];

let currentEndpoint = "users";
let currentMethod = "GET";

const displayMethod = document.getElementById("display-method");
const displayUrl = document.getElementById("display-url");
const bodySection = document.getElementById("body-section");
const requestBody = document.getElementById("request-body");
const idParamGroup = document.getElementById("id-param-group");
const idParam = document.getElementById("id-param");
const idLabel = document.getElementById("id-label");
const userIdGroup = document.getElementById("user-id-param-group");
const userIdParam = document.getElementById("user-id-param");
const sendBtn = document.getElementById("send-btn");
const copyBtn = document.getElementById("copy-btn");
const responseOut = document.getElementById("response-output");
const statusBadge = document.getElementById("status-badge");
const responseTime = document.getElementById("response-time");

// Select endpoint from sidebar
document.querySelectorAll(".endpoint-item").forEach((item) => {
  item.addEventListener("click", function () {
    document
      .querySelectorAll(".endpoint-item")
      .forEach((i) => i.classList.remove("active"));
    this.classList.add("active");

    currentEndpoint = this.dataset.endpoint;
    currentMethod = this.dataset.method;

    // Update method badge
    displayMethod.textContent = currentMethod;
    displayMethod.className = "method-badge " + currentMethod.toLowerCase();

    // Update URL display
    displayUrl.textContent = "api.php?endpoint=" + currentEndpoint;

    // Show/hide sections
    bodySection.style.display = currentMethod === "POST" ? "block" : "none";
    idParamGroup.style.display = needsId.includes(currentEndpoint)
      ? "block"
      : "none";
    userIdGroup.style.display = needsUserId.includes(currentEndpoint)
      ? "block"
      : "none";

    // Set ID label
    if (currentEndpoint === "user") idLabel.textContent = "user_id (?id=)";
    if (currentEndpoint === "loan") idLabel.textContent = "loan_id (?id=)";

    // Load default body for POST
    if (currentMethod === "POST" && defaults[currentEndpoint]) {
      requestBody.value = JSON.stringify(defaults[currentEndpoint], null, 2);
    } else {
      requestBody.value = "";
    }

    // Clear response
    responseOut.textContent = "// Click Send Request";
    statusBadge.textContent = "";
    statusBadge.className = "status-badge";
    responseTime.textContent = "";
  });
});

// Send request
sendBtn.addEventListener("click", async function () {
  sendBtn.textContent = "Sending...";
  sendBtn.disabled = true;

  let url = BASE + currentEndpoint;

  // Append GET params
  if (needsId.includes(currentEndpoint)) {
    const id = idParam.value.trim();
    if (!id) {
      responseOut.textContent = "// Please enter an ID.";
      sendBtn.textContent = "Send Request";
      sendBtn.disabled = false;
      return;
    }
    url += "&id=" + id;
  }

  if (needsUserId.includes(currentEndpoint)) {
    const uid = userIdParam.value.trim();
    if (!uid) {
      responseOut.textContent = "// Please enter a user_id.";
      sendBtn.textContent = "Send Request";
      sendBtn.disabled = false;
      return;
    }
    url += "&user_id=" + uid;
  }

  const options = {
    method: currentMethod,
    headers: { "Content-Type": "application/json" },
  };

  // Validate and attach POST body
  if (currentMethod === "POST") {
    try {
      JSON.parse(requestBody.value);
      options.body = requestBody.value;
    } catch (e) {
      responseOut.textContent =
        "// Invalid JSON in request body.\n// Check for missing commas or quotes.";
      statusBadge.textContent = "JSON Error";
      statusBadge.className = "status-badge status-error";
      sendBtn.textContent = "Send Request";
      sendBtn.disabled = false;
      return;
    }
  }

  const start = Date.now();

  try {
    const res = await fetch(url, options);
    const data = await res.json();
    const ms = Date.now() - start;

    responseOut.textContent = JSON.stringify(data, null, 2);
    statusBadge.textContent = res.status + (res.ok ? " OK" : " Error");
    statusBadge.className =
      "status-badge " + (res.ok ? "status-ok" : "status-error");
    responseTime.textContent = ms + "ms";
  } catch (err) {
    responseOut.textContent = "// Network error: " + err.message;
    statusBadge.textContent = "Failed";
    statusBadge.className = "status-badge status-error";
  }

  sendBtn.textContent = "Send Request";
  sendBtn.disabled = false;
});

// Copy response to clipboard
copyBtn.addEventListener("click", function () {
  navigator.clipboard.writeText(responseOut.textContent);
  copyBtn.textContent = "Copied!";
  setTimeout(() => (copyBtn.textContent = "Copy"), 1500);
});

// Trigger first item on load
document.querySelector(".endpoint-item").click();
