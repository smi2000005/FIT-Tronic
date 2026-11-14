document.addEventListener('DOMContentLoaded', () => {
    
    const questionModal = document.getElementById('questionModal');
    const openModalBtn = document.getElementById('openModalBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');

    if (openModalBtn && questionModal) {
        openModalBtn.addEventListener('click', () => questionModal.classList.remove('hidden'));
        closeModalBtn.addEventListener('click', () => questionModal.classList.add('hidden'));
        cancelModalBtn.addEventListener('click', () => questionModal.classList.add('hidden'));
        
        questionModal.addEventListener('click', (e) => {
            if (e.target === questionModal) questionModal.classList.add('hidden');
        });
    }

    const searchInput = document.getElementById('searchInput');
    const filterButtons = document.querySelectorAll('.filter-chip');
    const questionCards = document.querySelectorAll('.question-card');
    
    let activeCategory = 'All';
    let searchQuery = '';

    function filterQuestions() {
        questionCards.forEach(card => {
            const titleText = card.querySelector('h3').textContent.toLowerCase();
            const descText = card.querySelector('p').textContent.toLowerCase();
            const cardCategory = card.getAttribute('data-category');

            const matchesSearch = titleText.includes(searchQuery) || descText.includes(searchQuery);
            const matchesCategory = activeCategory === 'All' || cardCategory === activeCategory;

            if (matchesSearch && matchesCategory) {
                card.style.display = 'block';
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
            filterQuestions();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.toLowerCase().trim();
            filterQuestions();
        });
    }
});