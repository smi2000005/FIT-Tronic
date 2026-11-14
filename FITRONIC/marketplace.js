document.addEventListener('DOMContentLoaded', () => {
  
    const itemModal = document.getElementById('itemModal');
    const openItemBtn = document.getElementById('openItemModalBtn');
    const closeItemBtn = document.getElementById('closeItemModalBtn');
    const cancelItemBtn = document.getElementById('cancelItemModalBtn');

    if (openItemBtn && itemModal) {
        openItemBtn.addEventListener('click', () => itemModal.classList.remove('hidden'));
        closeItemBtn.addEventListener('click', () => itemModal.classList.add('hidden'));
        cancelItemBtn.addEventListener('click', () => itemModal.classList.add('hidden'));
        
        itemModal.addEventListener('click', (e) => {
            if (e.target === itemModal) itemModal.classList.add('hidden');
        });
    }

    const searchInput = document.getElementById('marketplaceSearch');
    const filterButtons = document.querySelectorAll('.filter-chip');
    const itemCards = document.querySelectorAll('.marketplace-item');

    let activeCategory = 'All';
    let searchQuery = '';

    function filterItems() {
        itemCards.forEach(card => {
            const titleText = card.querySelector('.project-title').textContent.toLowerCase();
            const descText = card.querySelector('.project-desc').textContent.toLowerCase();
            const cardCategory = card.getAttribute('data-category');

            const matchesSearch = titleText.includes(searchQuery) || descText.includes(searchQuery);
            const matchesCategory = activeCategory === 'All' || cardCategory === activeCategory;

            if (matchesSearch && matchesCategory) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            filterButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeCategory = btn.getAttribute('data-category');
            filterItems();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.toLowerCase().trim();
            filterItems();
        });
    }
});

const bidModal = document.getElementById('bidModal');

function openBidModal(itemId, itemName, currentPrice) {
    document.getElementById('bid_item_id').value = itemId;
    document.getElementById('bid_item_name').textContent = itemName;
    document.getElementById('bid_min_price').textContent = currentPrice.toFixed(2);
    
    document.getElementById('bid_amount').value = (currentPrice + 10).toFixed(2);
    document.getElementById('bid_amount').min = (currentPrice + 1).toFixed(2);
    
    bidModal.classList.remove('hidden');
}

function closeBidModal() {
    bidModal.classList.add('hidden');
}