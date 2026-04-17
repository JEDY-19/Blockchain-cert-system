// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

/**
 * @title CertificateRegistry
 * @notice On-chain commitments for certificates. Student name and matric are stored only as
 *         keccak256(utf8Bytes) values computed off-chain (same as Web3.utils.keccak256 over UTF-8).
 *         Plaintext PII must never be passed to this contract.
 */
contract CertificateRegistry {
    struct Certificate {
        bytes32 studentNameHash;
        bytes32 matricNumberHash;
        string department;
        string degreeClass;
        uint16 graduationYear;
        bytes32 sha256Hash;
        string ipfsCid;
        address issuedBy;
        uint256 issuedAt;
        bool exists;
        bool isRevoked;
        string revokeReason;
    }

    address public owner;
    address public pendingOwner;
    mapping(string => Certificate) private certificates;
    mapping(bytes32 => string) private hashToCertId;
    uint256 private issuedCount;
    uint256 private revokedCount;

    event OwnershipTransferProposed(address indexed currentOwner, address indexed proposedOwner);
    event OwnershipTransferred(address indexed previousOwner, address indexed newOwner);
    event CertificateIssued(
        string indexed certId,
        address indexed issuedBy,
        uint256 issuedAt,
        bytes32 sha256Hash,
        string ipfsCid,
        bytes32 studentNameHash,
        bytes32 matricNumberHash
    );
    event CertificateRevoked(string indexed certId, address indexed revokedBy, uint256 revokedAt, string reason);

    error NotOwner();
    error Exists();
    error NotFound();
    error AlreadyRevoked();
    error HashCollision();
    error InvalidPendingOwner();
    error ZeroAddress();

    modifier onlyOwner() {
        if (msg.sender != owner) revert NotOwner();
        _;
    }

    constructor() {
        owner = msg.sender;
    }

    function proposeOwnershipTransfer(address _newOwner) external onlyOwner {
        if (_newOwner == address(0)) revert ZeroAddress();
        pendingOwner = _newOwner;
        emit OwnershipTransferProposed(owner, _newOwner);
    }

    function acceptOwnership() external {
        if (msg.sender != pendingOwner) revert InvalidPendingOwner();
        address prev = owner;
        owner = pendingOwner;
        pendingOwner = address(0);
        emit OwnershipTransferred(prev, owner);
    }

    function issueCertificate(
        string calldata _certId,
        bytes32 _studentNameHash,
        bytes32 _matricNumberHash,
        string calldata _department,
        string calldata _degreeClass,
        uint16 _graduationYear,
        bytes32 _sha256Hash,
        string calldata _ipfsCid
    ) external onlyOwner {
        if (certificates[_certId].exists) revert Exists();
        string memory mappedId = hashToCertId[_sha256Hash];
        if (bytes(mappedId).length > 0 && keccak256(bytes(mappedId)) != keccak256(bytes(_certId))) {
            revert HashCollision();
        }
        hashToCertId[_sha256Hash] = _certId;
        certificates[_certId] = Certificate({
            studentNameHash: _studentNameHash,
            matricNumberHash: _matricNumberHash,
            department: _department,
            degreeClass: _degreeClass,
            graduationYear: _graduationYear,
            sha256Hash: _sha256Hash,
            ipfsCid: _ipfsCid,
            issuedBy: msg.sender,
            issuedAt: block.timestamp,
            exists: true,
            isRevoked: false,
            revokeReason: ""
        });
        issuedCount++;
        emit CertificateIssued(
            _certId, msg.sender, block.timestamp, _sha256Hash, _ipfsCid, _studentNameHash, _matricNumberHash
        );
    }

    function revokeCertificate(string calldata _certId, string calldata _reason) external onlyOwner {
        Certificate storage c = certificates[_certId];
        if (!c.exists) revert NotFound();
        if (c.isRevoked) revert AlreadyRevoked();
        c.isRevoked = true;
        c.revokeReason = _reason;
        revokedCount++;
        emit CertificateRevoked(_certId, msg.sender, block.timestamp, _reason);
    }

    function verifyCertificate(string calldata _certId)
        external
        view
        returns (
            bool isValid,
            bytes32 studentNameHash,
            bytes32 matricNumberHash,
            string memory department,
            string memory degreeClass,
            uint16 graduationYear,
            bytes32 sha256Hash,
            string memory ipfsCid,
            address issuedBy,
            uint256 issuedAt,
            bool isRevoked,
            string memory revokeReason
        )
    {
        Certificate storage c = certificates[_certId];
        if (!c.exists) {
            return (false, bytes32(0), bytes32(0), "", "", 0, bytes32(0), "", address(0), 0, false, "");
        }
        isValid = !c.isRevoked;
        return (
            isValid,
            c.studentNameHash,
            c.matricNumberHash,
            c.department,
            c.degreeClass,
            c.graduationYear,
            c.sha256Hash,
            c.ipfsCid,
            c.issuedBy,
            c.issuedAt,
            c.isRevoked,
            c.revokeReason
        );
    }

    function verifyByHash(bytes32 _hash) external view returns (bool exists, string memory certId) {
        certId = hashToCertId[_hash];
        exists = bytes(certId).length > 0;
    }

    function getStats() external view returns (uint256 issued, uint256 revoked) {
        return (issuedCount, revokedCount);
    }
}
