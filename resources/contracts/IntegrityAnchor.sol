// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

/**
 * @title IntegrityAnchor
 * @dev system-architecture.md §7.3, §7.8 / FR-6.3 / BR-36.
 * Permissioned on-premises append-only anchor registry on Hyperledger Besu (QBFT).
 * Holds cryptographic SHA-256 fingerprints, scope references, and block timestamps.
 *
 * Guaranteed: Zero payroll figures, employee wages, or personal data reach this contract (AC-6.3.6).
 * Once anchored, an entry is immutable and cannot be deleted or rewritten (BR-36).
 */
contract IntegrityAnchor {
    struct AnchorEntry {
        bytes32 payloadHash;
        string reference;
        uint256 timestamp;
        uint256 blockNumber;
        address sender;
    }

    // Mapping from payloadHash to AnchorEntry
    mapping(bytes32 => AnchorEntry) private _anchors;

    // Ordered list of all anchored payload hashes
    bytes32[] private _anchorKeys;

    // Emitted when a new anchor fingerprint is committed
    event Anchored(
        bytes32 indexed payloadHash,
        string reference,
        uint256 indexed chainPosition,
        uint256 timestamp,
        address sender
    );

    /**
     * Anchor a deterministic cryptographic payload hash to the immutable ledger.
     * @param payloadHash SHA-256 fingerprint formatted as bytes32.
     * @param ref Canonical reference identifier (e.g., "RUN:12", "REVERSAL:3").
     */
    function anchor(bytes32 payloadHash, string calldata ref) external {
        require(payloadHash != bytes32(0), "Anchor: payloadHash cannot be empty");
        require(_anchors[payloadHash].timestamp == 0, "Anchor: payloadHash already anchored");

        _anchors[payloadHash] = AnchorEntry({
            payloadHash: payloadHash,
            reference: ref,
            timestamp: block.timestamp,
            blockNumber: block.number,
            sender: msg.sender
        });

        _anchorKeys.push(payloadHash);

        emit Anchored(
            payloadHash,
            ref,
            _anchorKeys.length,
            block.timestamp,
            msg.sender
        );
    }

    /**
     * Retrieve anchor details for a specific payload hash.
     */
    function getAnchor(bytes32 payloadHash) external view returns (
        bytes32 hash,
        string memory reference,
        uint256 timestamp,
        uint256 blockNumber,
        address sender
    ) {
        AnchorEntry memory entry = _anchors[payloadHash];
        require(entry.timestamp > 0, "Anchor: hash not found");
        return (
            entry.payloadHash,
            entry.reference,
            entry.timestamp,
            entry.blockNumber,
            entry.sender
        );
    }

    /**
     * Total number of anchors committed.
     */
    function totalAnchors() external view returns (uint256) {
        return _anchorKeys.length;
    }
}
