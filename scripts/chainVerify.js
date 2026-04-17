/**
 * Server-side helper: outputs JSON for PHP chain_verify_bridge.php
 * Usage: node scripts/chainVerify.js <certId> <contractAddress> <rpcUrl>
 */
const { ethers } = require("ethers");
const fs = require("fs");
const path = require("path");

(async () => {
  const certId = process.argv[2];
  const addr = process.argv[3];
  const rpc = process.argv[4];
  if (!certId || !addr || !rpc) {
    console.log(
      JSON.stringify({ ok: false, error: "Usage: node chainVerify.js <certId> <contract> <rpc>" })
    );
    process.exit(0);
  }
  if (!/^0x[a-fA-F0-9]{40}$/.test(addr)) {
    console.log(JSON.stringify({ ok: false, error: "Invalid contract address" }));
    process.exit(0);
  }
  const artifactPath = path.join(
    __dirname,
    "..",
    "artifacts",
    "contracts",
    "CertificateRegistry.sol",
    "CertificateRegistry.json"
  );
  if (!fs.existsSync(artifactPath)) {
    console.log(
      JSON.stringify({ ok: false, error: "Artifact missing — run npx hardhat compile in project root" })
    );
    process.exit(0);
  }
  const { abi } = JSON.parse(fs.readFileSync(artifactPath, "utf8"));
  const provider = new ethers.JsonRpcProvider(rpc);
  const c = new ethers.Contract(addr, abi, provider);
  try {
    const r = await c.verifyCertificate(certId);
    const isRevoked = r.isRevoked;
    const isValid = r.isValid;
    console.log(
      JSON.stringify({
        ok: true,
        isValid: !!isValid,
        isRevoked: !!isRevoked,
        studentNameHash: r.studentNameHash != null ? String(r.studentNameHash) : "",
        matricNumberHash: r.matricNumberHash != null ? String(r.matricNumberHash) : "",
        sha256Hash: r.sha256Hash != null ? String(r.sha256Hash) : "",
        ipfsCid: r.ipfsCid,
        revokeReason: r.revokeReason,
      })
    );
  } catch (e) {
    console.log(JSON.stringify({ ok: false, error: e.shortMessage || e.message }));
  }
})();
