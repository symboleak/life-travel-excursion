/**
 * Life Travel Prototype JavaScript
 * Avec intégration de l'identité visuelle officielle et fonctionnalités améliorées
 */

document.addEventListener('DOMContentLoaded', function() {
    // Toggle mobile menu
    const menuToggle = document.querySelector('.menu-toggle');
    const siteNavigation = document.querySelector('.site-navigation');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            siteNavigation.classList.toggle('toggled');
            this.setAttribute('aria-expanded', this.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');
        });
    }
    
    // Search toggle functionality
    const searchToggle = document.querySelector('.search-toggle');
    const searchForm = document.querySelector('.search-form');
    
    if (searchToggle && searchForm) {
        searchToggle.addEventListener('click', function() {
            searchForm.classList.toggle('active');
            if (searchForm.classList.contains('active')) {
                searchForm.querySelector('input').focus();
            }
        });
        
        // Close search when clicked outside
        document.addEventListener('click', function(event) {
            if (!searchForm.contains(event.target) && !searchToggle.contains(event.target)) {
                searchForm.classList.remove('active');
            }
        });
    }
    
    // Month slider navigation
    const prevMonthBtns = document.querySelectorAll('.prev-month');
    const nextMonthBtns = document.querySelectorAll('.next-month');
    const currentMonthEls = document.querySelectorAll('.current-month, .month-selector h3');
    
    const months = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    let currentMonthIndex = 3; // April (0-indexed)
    
    function updateMonth() {
        currentMonthEls.forEach(el => {
            el.textContent = months[currentMonthIndex] + ' 2025';
        });
    }
    
    prevMonthBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            currentMonthIndex = (currentMonthIndex - 1 + 12) % 12;
            updateMonth();
        });
    });
    
    nextMonthBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            currentMonthIndex = (currentMonthIndex + 1) % 12;
            updateMonth();
        });
    });
    
    // Calendar day click
    const calendarDays = document.querySelectorAll('.calendar-day');
    
    calendarDays.forEach(day => {
        day.addEventListener('click', function() {
            // Remove selected class from all days
            calendarDays.forEach(d => d.classList.remove('selected'));
            
            // Add selected class to clicked day
            if (!this.classList.contains('empty')) {
                this.classList.add('selected');
            }
        });
    });
    
    // Vote buttons
    const voteButtons = document.querySelectorAll('.vote-button');
    
    voteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const voteOption = this.closest('.vote-option');
            const progressBar = voteOption.querySelector('.progress-bar');
            const voteCount = voteOption.querySelector('.vote-count');
            
            // Just for demo - increase vote count
            let currentWidth = parseInt(progressBar.style.width);
            if (isNaN(currentWidth)) {
                currentWidth = parseInt(progressBar.style.width) || 0;
            }
            
            const newWidth = Math.min(currentWidth + 5, 100);
            progressBar.style.width = newWidth + '%';
            voteCount.textContent = newWidth + '%';
            
            // Disable voting after click
            this.textContent = 'Voté';
            this.disabled = true;
            
            // Disable all other vote buttons
            voteButtons.forEach(btn => {
                btn.disabled = true;
                if (btn !== this) {
                    btn.textContent = 'Vote fermé';
                }
            });
        });
    });
    
    // Demo toggle for exclusive content (blog detail page)
    const loginButtons = document.querySelectorAll('.exclusive-content-restricted .button, .comments-area .button');
    
    loginButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Toggle exclusive content
            const exclusiveGallery = document.querySelector('.exclusive-gallery');
            const restrictedContent = document.querySelector('.exclusive-content-restricted');
            
            if (exclusiveGallery && restrictedContent) {
                exclusiveGallery.style.display = 'block';
                restrictedContent.style.display = 'none';
            }
            
            // Replace comments area with form
            const commentsArea = document.querySelector('.comments-area');
            if (commentsArea) {
                commentsArea.innerHTML = `
                    <div id="comments" class="comments-area">
                        <h3 class="comments-title">2 commentaires</h3>
                        <ol class="comment-list">
                            <li class="comment">
                                <article class="comment-body">
                                    <footer class="comment-meta">
                                        <div class="comment-author">
                                            <img src="img/avatar1.jpg" alt="avatar" class="avatar">
                                            <b class="fn">Marie Ngono</b>
                                        </div>
                                        <div class="comment-metadata">
                                            <time>15 Mars 2025</time>
                                        </div>
                                    </footer>
                                    <div class="comment-content">
                                        <p>Quelle aventure extraordinaire ! Les photos sont magnifiques et j'adore le récit de votre ascension. J'ai hâte de participer à la prochaine expédition !</p>
                                    </div>
                                </article>
                            </li>
                            <li class="comment">
                                <article class="comment-body">
                                    <footer class="comment-meta">
                                        <div class="comment-author">
                                            <img src="img/avatar2.jpg" alt="avatar" class="avatar">
                                            <b class="fn">Paul Biya</b>
                                        </div>
                                        <div class="comment-metadata">
                                            <time>16 Mars 2025</time>
                                        </div>
                                    </footer>
                                    <div class="comment-content">
                                        <p>Merci pour ce beau partage d'expérience. Ce fut un moment inoubliable et ces photos exclusives me rappellent de bons souvenirs!</p>
                                    </div>
                                </article>
                            </li>
                        </ol>
                        <div class="comment-respond">
                            <h3 class="comment-reply-title">Laisser un commentaire</h3>
                            <form class="comment-form">
                                <div class="comment-form-comment">
                                    <label for="comment">Commentaire</label>
                                    <textarea id="comment" name="comment" rows="5" required></textarea>
                                </div>
                                <p class="form-submit">
                                    <input type="submit" value="Publier" class="button">
                                </p>
                            </form>
                        </div>
                    </div>
                `;
            }
        });
    });
});
