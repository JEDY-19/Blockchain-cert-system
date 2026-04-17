/**
 * Prints Ethereum keccak256 of UTF-8 string (hex with 0x prefix). Used by PHP for chain/DB parity.
 * Usage: node scripts/keccakUtf8.js "some string"
 */
const { keccak256, toUtf8Bytes } = require("ethers");
const s = process.argv[2] ?? "";
process.stdout.write(keccak256(toUtf8Bytes(s)));
