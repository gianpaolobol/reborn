window.REBORN_STATE = {
  selectedPath: "print",
  selectedProvider: "Bologna 3D Lab",
  uploaded: false,
  role: "customer",
  set(key, value) {
    this[key] = value;
    window.dispatchEvent(new CustomEvent("reborn:state", { detail: { key, value } }));
  }
};
