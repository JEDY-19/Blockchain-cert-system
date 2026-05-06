const hre = require("hardhat");

async function main() {
  const privateKey = process.env.PRIVATE_KEY && process.env.PRIVATE_KEY.trim();
  if (!privateKey) {
    throw new Error(
      "PRIVATE_KEY is required to deploy. Set it in .env before running deploy:sepolia.",
    );
  }

  const deployer = new hre.ethers.Wallet(privateKey, hre.ethers.provider);
  const f = await hre.ethers.getContractFactory(
    "CertificateRegistry",
    deployer,
  );
  const c = await f.deploy();
  await c.waitForDeployment();
  const addr = await c.getAddress();
  console.log("CertificateRegistry deployed to:", addr);
  console.log("Set in .env: CONTRACT_ADDRESS=" + addr);
  console.log(
    "Update MySQL (run in phpMyAdmin / CLI; substitute your deployed address for the placeholder): UPDATE settings SET setting_value = ? WHERE setting_key = 'contract_address';",
  );
  console.log("Deployed address for manual bind:", addr);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
